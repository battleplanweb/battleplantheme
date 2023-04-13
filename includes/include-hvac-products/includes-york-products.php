<?php
/* Battle Plan Web Design - Add & Remove York Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-york-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );			
endif; 
*/
 
add_action( 'wp_loaded', 'add_york_products', 10 );
function add_york_products() {

	$brand = "york"; // lowercase
	$productImgAlt = "York Heating & Cooling Product"; 


	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (		
	
	// Air Conditioners
array ( 'post_title'	=>	'YXV 20 SEER2 Variable Capacity Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Premium Comfort with Lower Energy Use</span>

<p>The advanced design of the YORK® Affinity™ YXV 20 SEER2 Variable Capacity Air Conditioner dynamically adjusts capacity and airflow to precisely match changing needs for maximum comfort and minimized power consumption. In fact, these ENERGY STAR® Most Efficient qualified models can significantly cut energy costs compared to older, lower SEER units. A suite of leading-edge technologies provide quiet operation, maximized efficiency and long-term, premium comfort you expect from an industry leader. </p>

<ul>
	<li>ENERGY STAR® Most Efficient qualifying efficiency up to 20 SEER</li>
	<li>Unit pairing with YORK® Hx™ and Hx™3 Touch-screen Thermostats enhances comfort and improves control of your system</li>
	<li>Swept Wing Fan – A design adapted from aerospace engineering provides whisper-quiet operation by allowing air to flow smoothly and efficiently across the fan surface and edges.</li>
	<li>QuietDrive™ system silences vibrations with swept wing fan and composite base, while variable-speed compressor provides quiet and efficient operation</li>
	<li>Climate Set™ maximizes overall efficiency and provides improved comfort by fine-tuning the system to the indoor and outdoor environment</li>
	<li>Integrates with the Hx™3 Communicating Zoning System</li>
	<li>Durable Finish – A high quality powder paint finish rated at 1000 hrs. salt spray provides the ultimate protection from corrosion and harmful UV rays, ensuring a long-lasting, high quality appearance.</li>
</ul>', 
		'post_excerpt'	=>	'Our most efficient, most advanced air conditioner. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  1000,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/york%20brochure_print.pdf'),
		'image_name'	=>	'York-AC-01.jpg'		
),

array ( 'post_title'	=>	'YCG 17 SEER Single Stage Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Quieter, Optimized and Tested Tough </span>

<p>YORK® LX Series YCG Single-stage Air Conditioners offer big features in a small footprint. Thanks to a robust compressor and direct-drive fan design, sound is limited while reliability is enhanced. And the compact cabinet means the YCG is a perfect fit, even when space is limited. Designed, engineered and assembled in the United States, YCG air conditioners offer energy savings and lasting performance for years to come.</p>

<ul>
	<li>Single-phase and three-phase available (TCG)</li>
	<li>Durable, steel-extruded louver coil guards provide protection against coil damage</li>
	<li>Reliable Operation - ECM ball bearing fan motors provide superior performance in extreme temperatures.</li>
	<li>Top Discharge - Warm air is blown up, away from the structure and any landscaping and allows compact location on multi-unit applications.</li>
	<li>Uses R-410A refrigerant to prevent ozone depletion</li>
	<li>Long-lasting, powder-coat paint provides a durable, automative-quality finish</li>
	<li>Environmentally Friendly - CFC-free R-410A refrigerant delivers environmentally friendly performance with zero ozone depletion.</li>
	<li>Low Operating Sound Levels - Specific sound and vibration development tests provide a design sound performance of 74 dBA or lower. Swept-wing fan blades are featured on units 2.5-Tons and higher.</li>
</ul>', 
		'post_excerpt'	=>	'More performance in less space.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1010,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'air-conditioners', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/publ-7693-d-0916.pdf'),
		'image_name'	=>	'York-AC-02.jpg'		
),

array ( 'post_title'	=>	'YC2F 15.2 SEER2 Air Conditioner',
	   	'post_content' 	=>	'<span class="descriptionText">Quieter, Optimized and Tested Tough </span>

<p>YORK® YC2F Air Conditioner offers greater efficiency and performance in a small footprint. Energy Star-rated with up to 17.5 SEER2 efficiency, and 2-stage options for larger tonnages, this unit offers incredible value and performance at an affordable price. Innovative fan design provides quieter sound as low as 72 dBA. Designed for easy installation and servicing and backed by our industry-leading warranty, the YC2F provides lasting comfort and peace of mind for years to come. </p>

<ul>
	<li>Energy Star rated, with 2-stage performance on larger tonages (3.5-5 ton)</li>
	<li>Durable, steel-extruded louver coil guards provide protection against coil damage</li>
	<li>Uses R-410A refrigerant to prevent ozone depletion</li>
	<li>Long-lasting, powder-coat paint provides a durable, automative-quality finish</li>
</ul>', 
		'post_excerpt'	=>	'Our most efficient, most advanced air conditioner. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  1020,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/dx2209001ssy_yc2f_15.2%20seer2%20ac%20sell%20sheets_standard_york_digital.pdf'),
		'image_name'	=>	'York-AC-02.jpg'		
),
		
		
		
		
	
	// Heat Pumps
array ( 'post_title'	=>	'YHM 16 SEER Modulating Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">A Higher Level of Comfort</span>
			
<p>Maximize your comfort while minimizing energy bills with the YORK® LX Series YHM 16 SEER Modulating Heat Pump. This split-system heat pump will automatically adjust capacity and airflow to precisely match the ever-changing comfort requirements of your home, maximizing comfort while minimizing energy bills. Pair it with YORK® variable-speed furnaces or air handlers to maximize year-round comfort and performance. </p>
<ul>
	<li>Advanced control system Demand Defrost minimizes defrost cycles, reducing energy costs while maintaining comfort</li>
    <li>Unit can be matched with a YORK® residential gas furnace to create a dual-fuel comfort system that automatically switches between heat sources based on energy costs or capacity</li>
    <li>Reliable Operation – Ball bearing fan motors provide superior performance in extreme temperatures. Factory installed accumulator protects the compressor while operating across a wide range of conditions.</li>
    <li>An impact-resistant, powder-painted, galvanized steel, two-piece extruded louver coil guard protects your investment, yet is easy to remove for cleaning</li>
    <li>Automotive-grade, powder-paint finish is 1,000-hours salt spray rated to provide years of corrosion-free performance</li>
    <li>Protected Compressor - Compressors are protected by the system high and low pressure switches. The liquid line filter-drier is factory installed to protect the system against moisture and contaminates.</li>
    <li>Top Discharge - Warm air is blown up, away from the structure and any landscaping and allows compact location on multi-unit applications.</li>
</ul>', 
		'post_excerpt'	=>	'High-Efficiency Split System Heat Pump',
		'post_type'     =>	'products',
		'menu_order'  	=>  1100,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/publ-7696-d-0916.pdf'),
		'image_name'	=>	'York-HP-01.jpg'		
),		
		
array ( 'post_title'	=>	'YH2F 15.2 SEER2 1 & 2-Stage Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">Reliable, All Season Performance. Year Round Energy Savings. </span>
			
<p>The YORK® LX Series YH2F meets Energy Star/CEE Tier 1 efficiency requirements and is eligible for tax and rebate incentives through the Inflation Reduction Act (IRA). Designed to deliver year-round comfort, the ENERGY STAR® qualifying 15.2 SEER2 efficiency means you will spend less on energy bills. Backed by the Good Housekeeping Seal, this split-system heat pump is specifically designed to be matched with YORK® indoor coils, furnaces and air handlers for a complete system solution. </p>
<ul>
	<li>Automotive-grade, powder-paint finish is 1,000-hours salt spray rated to provide years of corrosion-free performance</li>
    <li>An impact-resistant, powder-painted, galvanized steel, two-piece extruded louver coil guard protects your investment, yet is easy to remove for cleaning</li>
    <li>Single- and three-phase models available</li>
    <li>Unit can be matched with a YORK® residential gas furnace to create a dual-fuel comfort system that automatically switches between heat sources based on energy costs or capacity</li>
    <li>Two-stage (except 1.5 Ton) scroll compressor is a robust, time-tested design that can stand up to the rigors presented while comfort conditioning a home</li>
</ul>', 
		'post_excerpt'	=>	'Efficient, dependable, year-round comfort',
		'post_type'     =>	'products',
		'menu_order'  	=>  1110,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'heat-pumps', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/dx2207002ay_res_guide_york_1_digital.pdf'),
		'image_name'	=>	'York-HP-01.jpg'		
),		

array ( 'post_title'	=>	'YH2E 14.3 SEER2 Single-Stage Heat Pump',
		'post_content' 	=>	'<span class="descriptionText">Dependable, Efficient Performance </span>
			
<p>Get the next generation of efficiency and energy savings with the proven performance of the YORK® YH2E 14.3 SEER2 1-2-Stage Heat Pump. This efficient heat pump meets 2023 Department of Energy (DOE) minimums with 14.3 SEER2 cooling, and 8.0 HSPF2 heating efficiency requirements to keep your family cool in summer and warm in winter. The YORK® YH2E reduces your energy bills replacing older, less efficient equipment. The YORK® YH2E offers dependable performance, energy efficiency and operating economy – all backed by the Good Housekeeping Seal. </p>
<ul>
	<li>Automotive-grade, powder-paint finish is 1,000-hours salt spray rated to provide years of corrosion-free performance</li>
    <li>An impact-resistant, powder-painted, galvanized steel, two-piece extruded louver coil guard protects your investment, yet is easy to remove for cleaning</li>
    <li>Single- and three-phase models available</li>
    <li>Top Discharge - Warm air is blown up, away from the structure and any landscaping and allows compact location on multi-unit applications.</li>
    <li>Unit can be matched with a YORK® residential gas furnace to create a dual-fuel comfort system that automatically switches between heat sources based on energy costs or capacity</li>
    <li>All models will have a two-stage compressor with the exception of the 1.5 ton model which will have a single-stage compressor</li>
    <li>Environmentally Friendly - CFC-free R-410A refrigerant delivers environmentally friendly performance with zero ozone depletion.</li>
    <li>Low Operating Sound Levels - Developed using CFD and FEA tools, the sturdy cabinet and top design provides sound performance of 76 dBA or lower. Compatible accessories for further sound reduction are also available.</li>
</ul>', 
		'post_excerpt'	=>	'Efficient, dependable, year-round comfort. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  1120,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>''),
		'image_name'	=>	'York-AC-02.jpg'		
),		
		
		
		
		
	
	// Furnaces
array ( 'post_title'	=>	'YP9C 98% Modulating Gas Furnace',
		'post_content' 	=>	'<span class="descriptionText">A Higher Standard for Efficiency </span>

<p>The YORK® Affinity™ Gas Furnaces are redefining home comfort through an unparalleled combination of efficiency, convenience and reliability. The Affinity™ YP9C is designed to make home comfort more personal than ever before. As our most premium furnace, the YP9C features innovative technology that improves performance, provides consistent comfort and optimizes efficiency. </p>
<ul>
	<li>Modulating burner design continuously adjusts its heating output in 1% increments to save fuel, while the integrated ClimaTrak™ comfort system tailors operation to the needs of your locale.</li>
	<li>ENERGY STAR® Most Efficient qualifying efficiency up to 98% Annual Fuel Utilization Efficiency</li>
    <li>Integrated self-diagnostic control module</li>
    <li>Sharp Edges Eliminated By Folding and Flattening the Sheet Metal.</li>
    <li>Variable-speed ECM fan motor provides quiet, efficient circulation</li>
    <li>Unit design engineered for natural gas or propane applications</li>
    <li>Integrates with the Hx™3 Communicating Zoning System</li>
    <li>Fully-Gasketed, Independent Access Doors W/View Ports, Retail Appearance.</li>
</ul>', 
		'post_excerpt'	=>	'The most advanced, most efficient furnace we offer. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  1200,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'furnaces', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/publ-6210-f-0817.pdf'),
		'image_name'	=>	'York-F-01.jpg'		
),


array ( 'post_title'	=>	'TM9V 96% AFUE Two Stage Variable Speed Furnace',
		'post_content' 	=>	'<span class="descriptionText">Advanced Efficiency. Optimal Comfort. </span>

<p>YORK® TM9V Compact Gas Furnaces are designed to improve performance and efficiency. With a two-stage burner and a variable-speed ECM fan motor, this unit can be fine-tuned to your specific climate needs. This is an ideal gas furnace for your home, with hassle-free installation in tight spaces. Designed, engineered and assembled in the United States, YORK® LX Series Furnaces use state-of-the-art manufacturing processes to provide world-class quality at competitive prices. </p>
<ul>
	<li>ENERGY STAR® qualifying efficiency of up to 96% AFUE</li>
	<li>High-efficiency, variable-speed ECM fan motor provides quiet, effective air circulation, and a compact 33-inch height fits into tight spaces</li>
    <li>Integrated self-diagnostic control module</li>
    <li>All models are convertable to use propane (LP) gas.</li>
    <li>Two-stage burner adjusts heat levels to match your comfort level</li>
    <li>Unit design engineered for natural gas or propane applications</li>
    <li>Integrates with the Hx™3 Communicating Zoning System</li>
    <li>Electronic Hot Surface Ignition saves fuel cost with increased dependability and reliability.</li>
</ul>', 
		'post_excerpt'	=>	'ENERGY STAR® qualifying efficiency in a compact design. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  1210,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'furnaces', 'product-class'=>'great'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/yorklxgasfurn-bro.pdf'),
		'image_name'	=>	'York-F-02.jpg'		
),

array ( 'post_title'	=>	'TL8E 80% AFUE Single Stage, Ultra-Low NOx Furnace',
		'post_content' 	=>	'<span class="descriptionText">High Efficiency, Lower Emissions </span>

<p>YORK® TL8E Compact Gas Furnaces are engineered with advanced burner technology for ultra-low emissions. The high-efficiency ECM fan motor ensures comfort while providing energy cost savings.  Designed, engineered and assembled in the United States, YORK® LX Series Furnaces use state-of-the-art manufacturing processes to provide world-class quality at competitive prices. </p>
<ul>
	<li>Advanced burner technology for ultra-low emissions</li>
	<li>Compact 33-inch height fits into tight spaces</li>
    <li>Energy-saving, hot-surface ignition</li>
    <li>Single-stage burner and standard high-efficiency ECM fan motor ensure comfort</li>
    <li>Complements both single-stage and two-stage outdoor equipment</li>
    <li>Fully insulated cabinet design</li>
</ul>', 
		'post_excerpt'	=>	'High efficiency and ultra-low emissions. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  1220,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'furnaces', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/dx2301005b_res_buying_guide_yk_digital.pdf'),
		'image_name'	=>	'York-F-01.jpg'		
),

		
		
		
	
	// Air Handlers
array ( 'post_title'	=>	'AVV Communicating, Constant CFM Air Handler',
		'post_content' 	=>	'<span class="descriptionText">Dynamic Control for Maximum Comfort</span>

<p>The YORK® Affinity™ Series AVV Residential Air Handler circulates air quietly and precisely, providing you with maximum comfort and minimized power consumption. Variable-capacity technology dynamically adjusts capacity and airflow to accurately match your home\'s changing comfort needs, while the exclusive MaxAlloy™ corrosion-resistant aluminum coils with a system-matched, electronic expansion valve (EEV) provide even temperatures and superior humidity control. </p>
<ul>
	<li>Factory-installed electronic expansion valve provides consistent capacity and efficiency across a wide band of operation, and multi-position flow as standard provides everything needed for all installed positions without additional kits</li>
    <li>Less than 2% air leakages at 1.0" esp. ensures only conditioned air moves through your home and unconditioned air isn\'t introduced into the system</li>
    <li>MaxAlloy™ coil features advanced corrosion-resistant design for long life</li>
    <li>Built-in filter rack accepts 1.0" disposable and cleanable air filters</li>
    <li>Integrates with the Hx™3 Communicating Zoning System</li>
</ul>', 
		'post_excerpt'	=>	'Superior year-round comfort control.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1300,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'air-handlers', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/york%20brochure_print.pdf'),
		'image_name'	=>	'York-AH-01.jpg'		
),

array ( 'post_title'	=>	'JHVT Variable Speed Air Handler',
		'post_content' 	=>	'<span class="descriptionText">A quiet and efficient air handler for almost any installation </span>

<p>Reduce energy costs with the high-efficiency, next-generation blower and “A” coil design of the YORK® JHVT Variable Speed Air Handler. Its advanced, variable-speed ECM blower uses up to 80% less electricity than traditional technologies. Optimized to comply with new DOE 2023 regulations, JHVT air handlers help your system meet more stringent minimum efficiency standards. Save even more energy with a fully insulated cabinet that meets strict requirements against leakage. The JHVT also reduces allergens with both filter and indoor air quality options. Pair the JHVT with a YORK® air conditioner or heat pump. </p>
<ul>
	<li>Standard multi-position designed for all installation positions</li>
    <li>Compatible with a communicating system featuring the Hx™3 Touch Screen Thermostat, the Universal Thermostat Adapter or for pairing with select conventional thermostats</li>
    <li>Composite, low-water-retention drain pans reduce the possibility of mold or bacteria buildup</li>
    <li>Less than 2% air leakages at 1.0" esp. ensures only conditioned air moves through your home and unconditioned air isn\'t introduced into the system</li>
    <li>MaxAlloy™ “A” coil is built to deliver lasting performance, efficient refrigerant flow and reliability</li>
    <li>Built-in filter rack accepts 1.0” disposable and cleanable air filters and features sliding latch design for quick and easy access</li>
</ul>', 
		'post_excerpt'	=>	'Superior year-round comfort control.',
		'post_type'     =>	'products',
		'menu_order'  	=>  1310,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'air-handlers', 'product-class'=>'best'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/jciy12211-york-airhandler%E2%80%93sellsheet-d23i-lowresviewonly.pdf'),
		'image_name'	=>	'York-AH-02.jpg'		
),

array ( 'post_title'	=>	'AP Fixed Speed Multi Position Air Handler',
		'post_content' 	=>	'<span class="descriptionText">Moves Air to Complete Your Home Comfort System </span>

<p>The YORK® AP Standard-efficiency, Multi-speed Air Handler features a MaxAlloy™ aluminum coil built to deliver lasting performance, efficiency and reliability. The one-piece, multi-position cabinet construction provides installation flexibility. </p>
<ul>
	<li>Composite, low-water-retention drain pans reduce the possibility of mold or bacteria buildup</li>
    <li>Standard multi-position provides everything needed for all installed positions – no additional kits required</li>
    <li>Built-in filter rack accepts 1.0" disposable and cleanable air filters</li>
    <li>Robust design is rigorously tested well beyond industry standards to ensure long-lasting performance</li>
    <li>Less than 2% air leakages at 1.0" esp. ensures only conditioned air moves through your home and unconditioned air isn\'t introduced into the system</li>
    <li>MaxAlloy™ coil features advanced corrosion-resistant design for long life</li>
</ul>', 
		'post_excerpt'	=>	'Provides reliable, economical comfort. ',
		'post_type'     =>	'products',
		'menu_order'  	=>  1320,
		'tax_input'		=>  array('product-brand'=>'york', 'product-type'=>'air-handlers', 'product-class'=>'good'),
		'meta_input'	=>	array('brochure'=>'https://files.hvacnavigator.com/p/publ-7701-a-0715.pdf'),
		'image_name'	=>	'York-AH-01.jpg'		
),
			
);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>