<?php
/* Battle Plan Web Design - Add & Remove Comfortmaker Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
 	require_once get_template_directory().'/includes/include-hvac-products/includes-comfortmaker-products.php';
	updateOption( 'bp_product_upload_2022_08_11', 'completed', false );
endif;
*/

add_action( 'wp_loaded', 'add_comfortmaker_products', 10 );
function add_comfortmaker_products() {

	$brand = "comfortmaker"; // lowercase
	$productImgAlt = "Comfortmaker Heating & Cooling Product";


	/*
	$removeProducts = array('silver-15-heat-pump', 'american-standard-80-furnace', 'silver-95-furnace');
	*/


	$addProducts = array (

	// Air Conditioners
     array ( 'post_title'	=>	'Ion™ 21 Variable-Speed Air Conditioner',
                  'post_content' 	=>	'<span class="descriptionText">Enjoy comfort without compromise, thanks to our variable-speed air conditioner. </span>

     <p>This whisper-quiet system senses changing conditions and adapts so you can stay comfy with outstanding efficiency. And when it gets extra hot and sticky outside, you\'ll feel the difference with enhanced dehumidification inside. For maximum performance along with the convenience of remote access, pair it with a complete communicating system, including the energy-smart Ion™ Black System Control with Wi-Fi® capability.</p>

     <ul>
          <li><b>Variable frequency drive</b> powers the compressor and controls communication between components to optimize comfort and efficiency.</li>
          <li><b>Variable-speed compressor</b> provides our best temperature and summer humidity control.</li>
          <li><b>Variable-speed fan</b> works with compressor for best levels of quiet, efficient operation. Swept fan blade design improves airflow, enhances performance and reduces sound levels.</li>
          <li><b>Weather and debris protection</b>, built with tight wire grille and protective corner posts.</li>
          <li><b>Ion™ Black System Control</b> allows precise temperature control and humidity control along with programmable features to further customize your comfort.</li>
          <li>Quiet performance (as low as 55 decibels)</li>
          <li>High-efficiency variable-speed fan works with compressor for our best levels of quiet, efficient operation</li>
          <li>Wi-Fi® enabled remote access with the Ion™ Gray Smart Thermostat</li>
          <li>10-Year No Hassle Replacement Limited Warranty™</li>
          <li>10-Year Parts Limited Warranty</li>
     </ul>',
               'post_excerpt'	=>	'Experience the soothing, cool comfort of our quietest, most advanced air conditioner. This smart, variable-speed system matches the smallest changes in conditions to ensure superior comfort and savings.',
               'post_type'     =>	'products',
               'menu_order'  	=>  1000,
               'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'air-conditioners', 'product-class'=>'best'),
               'meta_input'	=>	array('brochure'=>''),
               'image_name'	=>	'comfortmaker-2.webp'
     ),

     array ( 'post_title'	=>	'Performance 18 Two-Stage Air Conditioner',
               'post_content' 	=>	'<span class="descriptionText">Who says you can\'t have premium comfort on a bargain budget?</span>

     <p>This cost-efficient air conditioner beats the summer heat with longer cooling cycles to keep temperatures even throughout the house and summer humidity at a minimum. And when temperatures soar, it kicks into high gear to keep you from working up a sweat. With up to 18 SEER2 cooling efficiency it can put your electric bill on ice as well.</p>

     <ul>
          <li><b>Two-stage compressor</b> for improved temperature and summer humidity control.</li>
          <li><b>Weather and debris protection</b>, built with tight wire grille and protective corner posts.</li>
          <li><b>Durable design</b> for lasting performance, including corrosion resistance.</li>
          <li>Quiet performance (as low as 69 decibels)</li>
          <li>High-efficiency variable-speed fan works with compressor for our best levels of quiet, efficient operation</li>
          <li>Wi-Fi® enabled remote access with the Ion™ Gray Smart Thermostat</li>
          <li>10-Year Parts Limited Warranty</li>
     </ul>',
               'post_excerpt'	=>	'Sit back and take in the quiet comfort of our affordable two-stage air conditioner. You\'ll enjoy smooth, consistent comfort when it\'s hot outside.',
               'post_type'     =>	'products',
               'menu_order'  	=>  1010,
               'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'air-conditioners', 'product-class'=>'better'),
               'meta_input'	=>	array('brochure'=>''),
               'image_name'	=>	'comfortmaker-1.webp'
     ),

     array ( 'post_title'	=>	'Performance 16 Conditioner',
               'post_content' 	=>	'<span class="descriptionText">You don\'t need to feel the strain of hot weather on your budget.</span>

     <p>You can have ENERGY STAR® qualified performance from a price-friendly unit. This single-stage air conditioner will provide years of reliable performance and help you keep the heat and humidity at bay all summer long.</p>

     <ul>
          <li><b>Single-stage compressor</b></li>
          <li><b>Single-speed fan</b></li>
          <li><b>Weather and debris protection</b>, built with tight wire grille and protective corner posts.</li>
          <li><b>Durable design</b> for lasting performance, including corrosion resistance.</li>
          <li>Quiet performance (as low as 71 decibels)</li>
          <li>Wi-Fi® enabled remote access with the Ion™ Gray Smart Thermostat</li>
          <li>10-Year Parts Limited Warranty</li>
     </ul>',
               'post_excerpt'	=>	'You can enjoy high-efficiency energy savings in a budget-friendly unit with this single-stage scroll compressor air conditioner. It\'s a solid performer all the way around.',
               'post_type'     =>	'products',
               'menu_order'  	=>  1020,
               'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'air-conditioners', 'product-class'=>'good'),
               'meta_input'	=>	array('brochure'=>''),
               'image_name'	=>	'comfortmaker-1.webp'
     ),




	// Heat Pumps
     array ( 'post_title'	=>	'Ion™ 23 Variable-Speed Heat Pump',
               'post_content' 	=>	'<span class="descriptionText">Enjoy comfort without compromise, thanks to our highest efficiency heat pump with fully variable capabilities. </span>

     <p>This whisper-quiet system efficiently adapts to changing conditions so you stay comfy with outstanding efficiency. Use it year-round with enhanced summer humidity control and warm winter heating even in colder temperatures with up to 12 HSPF2. Or, combine it with a compatible Ion™ gas furnace and thermostat to gain dual fuel heating efficiency in more extreme climates. You can also enjoy the convenience of remote access and the assurance of best levels of performance and comfort management when you pair it with a complete communicating system, including the energy-smart Ion™ Black System Control with Wi-Fi® capability.</p>

     <ul>
          <li><b>Variable frequency drive</b> powers the compressor and controls communication between components to optimize comfort and efficiency.</li>
          <li><b>Advanced electronic control</b> quietly switches between heating, cooling and defrost modes.</li>
          <li><b>Variable-speed compressor</b> provides our best temperature and summer humidity control.</li>
          <li><b>Variable-speed fan</b> works with compressor for best levels of quiet, efficient operation. Swept fan blade design improves airflow, enhances performance and reduces sound levels.</li>
          <li><b>Weather and debris protection</b>, built with tight wire grille and protective corner posts.</li>
          <li><b>Ion™ Black System Control</b> allows precise temperature control and humidity control along with programmable features to further customize your comfort.</li>
          <li>Quiet performance (as low as 55 decibels)</li>
          <li>Variable-speed fan works with compressor for best levels of quiet, efficient operation</li>
          <li>Wi-Fi® enabled remote access with the Ion™ Gray Smart Thermostat</li>
          <li>10-Year No Hassle Replacement Limited Warranty™</li>
          <li>10-Year Parts Limited Warranty</li>
     </ul>',
          'post_excerpt'	=>	'Experience the smooth, quiet superior comfort of our most advanced heat pump. This smart, variable-speed system matches the smallest changes in conditions to ensure comfort and savings.',
          'post_type'     =>	'products',
          'menu_order'  	=>  2000,
          'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'heat-pumps', 'product-class'=>'best'),
          'meta_input'	=>	array('brochure'=>''),
          'image_name'	=>	'comfortmaker-3.webp'
     ),

     array ( 'post_title'	=>	'Performance 18 Two-Stage Heat Pump',
               'post_content' 	=>	'<span class="descriptionText">Who says you can\'t have premium comfort on a bargain budget?</span>

     <p>This cost-efficient heat pump beats the summer heat and winter\'s icy chill with longer cooling and heating cycles. It keeps temperatures even throughout the house and summer humidity at a minimum. And when outdoor conditions are more extreme, it kicks into high gear to keep you cozy all year long. With up to 18 SEER2 cooling and up to 8.5 HSPF2 heating efficiency, it can put your electric bills on ice as well.</p>

     <ul>
          <li>Quiet performance (as low as 69 decibels)</li>
          <li>Dual fuel capable with a compatible gas furnace and thermostat for energy-saving heating</li>
          <li>Durably built to withstand bad weather and debris</li>
          <li>Designed for corrosion resistance and lasting performance</li>
          <li>Wi-Fi® enabled remote access with the Ion™ Gray Smart Thermostat</li>
          <li>10-Year Parts Limited Warranty</li>
     </ul>',
          'post_excerpt'	=>	'Sit back and take in the comfort all year long with our affordable two-stage heat pump. You\'ll enjoy smooth, consistent indoor temperatures when it\'s cold outside.',
          'post_type'     =>	'products',
          'menu_order'  	=>  2010,
          'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'heat-pumps', 'product-class'=>'better'),
          'meta_input'	=>	array('brochure'=>''),
          'image_name'	=>	'comfortmaker-1.webp'
     ),

     array ( 'post_title'	=>	'Performance 16 Heat Pump',
               'post_content' 	=>	'<span class="descriptionText">This heat pump features a single-stage scroll compressor with cooling efficiencies up to 16 SEER2.</span>

     <p>Its quiet electric heating and cooling can be used year round in warmer climates, or pair it with a compatible gas furnace and thermostat to gain dual fuel heating efficiency in colder climates. It is ENERGY STAR® qualified on select sizes which means you can enjoy money-saving, efficient comfort.</p>

     <ul>
          <li>Quiet performance (as low as 70 decibels)</li>
          <li>Single-stage compressor operation</li>
          <li>Dual fuel capable with a compatible gas furnace and thermostat for energy-saving heating</li>
          <li>Durably built to withstand bad weather and debris</li>
          <li>Designed for corrosion resistance and lasting performance</li>
          <li>Wi-Fi® enabled remote access with the Ion™ Gray Smart Thermostat</li>
          <li>10-Year Parts Limited Warranty</li>
     </ul>',
          'post_excerpt'	=>	'Save money while staying comfortable year-round with a heat pump that fits your budget yet has efficiency levels that make it ENERGY STAR® qualified on select sizes.',
          'post_type'     =>	'products',
          'menu_order'  	=>  2020,
          'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'heat-pumps', 'product-class'=>'good'),
          'meta_input'	=>	array('brochure'=>''),
          'image_name'	=>	'comfortmaker-1.webp'
     ),








	// Air Handlers
     array ( 'post_title'	=>	'Ion™ System Variable-Speed Fan Coil',
               'post_content' 	=>	'<span class="descriptionText">Get increased comfort and efficiency with our variable-speed communicating fan coil. </span>

     <p>It can help your system operate more efficiently by creating more consistent airflow throughout your home, helping gain SEER2 and HSPF2 rating points for some systems. Enjoy enhanced comfort levels with more even indoor temperatures all year long. And when temperatures are smoldering outside, you can feel a little cooler inside as well with better humidity control capabilities. Create a complete communicating system for even more control when you pair it with the Ion™ System Control with Wi-Fi® capability and communicating outdoor unit.</p>

     <ul>
          <li>Communicating variable-speed fan blower that can quietly deliver more even levels of temperature and enhanced humidity control</li>
          <li>Efficiency-optimizing Thermostatic Expansion Valve (TXV)</li>
          <li>Compatible with two-stage outdoor units</li>
          <li>Corrosion-resistant all-aluminum coil</li>
          <li>Convenient washable filter</li>
          <li>Air purifier and dehumidifier compatible</li>
          <li>Fully insulated cabinet</li>
          <li>Corrosion-free sloped drain pan design helps reduce mold and bacteria buildup</li>
          <li>10-Year No Hassle Replacement Limited Warranty™</li>
          <li>10-Year Parts Limited Warranty</li>
     </ul>',
          'post_excerpt'	=> 'Enjoy energy efficient performance with our variable-speed fan coil with communicating capability. It\'s compatible with two-stage air conditioners and heat pumps to enhance efficiency and deliver increased comfort levels.',
          'post_type'     =>	'products',
          'menu_order'  	=>  3000,
          'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'air-handlers', 'product-class'=>'best'),
          'meta_input'	=>	array('brochure'=>''),
          'image_name'	=>	'comfortmaker-4.webp'
     ),

     array ( 'post_title'	=>	'QuietComfort® Fan Coil',
               'post_content' 	=>	'<span class="descriptionText">Reducing energy use with your air conditioner or heat pump is probably something you won\'t mind hearing.</span>

     <p>Our variable-speed fan coil can move the air through your home more efficiently and may lead to a boost in SEER2 rating (cooling efficiency) on some systems. Savor enhanced comfort levels with temperature and humidity control , even when temperatures are smoldering.</p>

     <ul>
          <li>Variable-speed blower that can quietly deliver more even levels of temperature and enhanced humidity control</li>
          <li>Efficiency-optimizing Thermostatic Expansion Valve (TXV)</li>
          <li>Compatible with two-stage outdoor units</li>
          <li>Corrosion-resistant all-aluminum coil</li>
          <li>Convenient washable filter</li>
          <li>Air purifier and dehumidifier compatible</li>
          <li>Fully insulated cabinet</li>
          <li>Corrosion-free sloped drain pan design helps reduce mold and bacteria buildup</li>
          <li>10-Year No Hassle Replacement Limited Warranty™</li>
          <li>10-Year Parts Limited Warranty</li>
     </ul>',
          'post_excerpt'	=> 'Savor enhanced comfort levels with temperature and humidity control, even when temperatures are smoldering.',
          'post_type'     =>	'products',
          'menu_order'  	=>  3010,
          'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'air-handlers', 'product-class'=>'better'),
          'meta_input'	=>	array('brochure'=>''),
          'image_name'	=>	'comfortmaker-4.webp'
          ),

     array ( 'post_title'	=>	'Performance Compact Fan Coil',
          'post_content' 	=>	'<span class="descriptionText">Comfort is key when you pair your outdoor unit with a compatible fan coil—and high efficiency is a plus.</span>

     <p>Our compact multi-speed fan coil delivers more comfort options while delivering energy-saving airflow. It’s designed for standard and tight spaces with multi-position installation to fit your home’s needs.</p>

     <ul>
     <li>Multi 5-speed high-efficiency ECM blower motor that can quietly deliver more even levels of temperature</li>
     <li>Narrow design to fit tight spaces</li>
     <li>Corrosion-resistant all-aluminum coil</li>
     <li>Fully insulated cabinet</li>
     <li>Corrosion-free sloped drain pan design helps reduce mold and bacteria buildup</li>
     <li>5-Year No Hassle Replacement Limited Warranty™</li>
     <li>10-Year Parts Limited Warranty</li>
     </ul>',
     'post_excerpt'	=> 'This multi-speed fan coil combines performance and compact size to deliver energy efficiency consistent comfort levels and a design to fit in tighter spaces.',
     'post_type'     =>	'products',
     'menu_order'  	=>  3020,
     'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'air-handlers', 'product-class'=>'good'),
     'meta_input'	=>	array('brochure'=>''),
     'image_name'	=>	'comfortmaker-4.webp'
     ),







	// Furnaces
     array ( 'post_title'	=>	'Performance 80 Gas Furnace',
          'post_content' 	=>	'<span class="descriptionText">As part of a complete, year-round comfort system, offers 18 blower motor speed options that your dealer can customize to deliver optimized airflow to your living spaces.</span>

     <ul>
     <li>Quiet performance</li>
     <li>Multi 18-speed blower motor that your dealer can customize for tailored home comfort.</li>
     <li>Dual fuel capable with a compatible heat pump and thermostat for energy-saving heating performance</li>
     <li>Air purifier and humidifier compatible</li>
     <li>Wi-Fi® enabled remote access with the Ion™ Gray Smart Thermostat</li>
     <li>10-Year Parts Limited Warranty upon timely registration</li>
     <li>20-Year Heat Exchanger Limited Warranty</li>
     </ul>',
     'post_excerpt'	=> 'Get efficient performance with our value gas furnace that deliverers single-stage operation and an electrically efficient ECM blower motor with 18 speed options for reliable temperature control and quiet performance.',
     'post_type'     =>	'products',
     'menu_order'  	=>  4000,
     'tax_input'		=>  array('product-brand'=>'comfortmaker', 'product-type'=>'furnaces', 'product-class'=>'good'),
     'meta_input'	=>	array('brochure'=>''),
     'image_name'	=>	'comfortmaker-5.webp'
     ),



);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>