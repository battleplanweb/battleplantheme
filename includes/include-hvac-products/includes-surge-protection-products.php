<?php
/* Battle Plan Web Design - Add & Remove Surge Protection Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
	add_action( 'wp_loaded', 'add_surge_protection_products', 10 );
	function add_surge_protection_products() {
		$brand = "surge-protection"; // lowercase
		$productImgAlt = "Surge Protection Product";

		$addProducts = array (
			***copy/paste: the arrays you want to include
		);
		require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
		updateOption( 'bp_product_upload_2022_08_11', 'completed', false );
	}
endif;
*/

/* PRODUCT OVERVIEW
[product-overview type="surge protection"]
*/

add_action( 'wp_loaded', 'add_surge_protection_products', 10 );
function add_surge_protection_products() {

	$brand = "surge-protection"; // lowercase
	$productImgAlt = "Surge Protection Product";

	//$removeImages = array('Amana-AH-01.jpg', 'Amana-F-02.jpg', 'Amana-F-01.jpg', 'Amana-02.jpg', 'Amana-01.jpg');

	//$removeProducts = array('asxv9-air-conditioner', 'asxc7-air-conditioner', 'asxh5-air-conditioner', 'asxh4-air-conditioner', 'aszv9-heat-pump', 'aszc7-heat-pump', 'aszh5-heat-pump', 'aszh4-heat-pump', 'amvc96-90-afue-gas-furnace', 'amvc8-advc8-80-afue-gas-furnace', 'am9s96-u-90-afue-gas-furnace', 'am9s80-80-afue-gas-furnace', 'amve-air-handler', 'ahve-air-handler', 'amst-air-handler' );

	$addProducts = array (

		array ( 'post_title'	=>	'Rectorseal RSH-50 Surge Protective Device',
				'post_content' 	=>	'<span class="descriptionText">Protect your home and HVAC equipment from damaging power fluctuations caused by electrical surges, brownouts, and other voltage disturbances. Added voltage protection helps safeguard sensitive components, reduce the risk of costly damage, and extend the life of your equipment.</span>

		<p>Designed to help protect HVAC equipment from damaging electrical surges, this Type 1 surge protector can be installed throughout the electrical system and is suitable for residential or commercial applications. It protects 120/240V single-phase outdoor condensing units and indoor air handlers, handling single surges up to 50,000 amps and repeated surges up to 10,000 amps, providing an added layer of protection for valuable HVAC components.</p>

		<ul>
			<li>Long-Lasting Gas Discharge Tube Technology</li>
			<li>TFMOV Technology</li>
			<li>Limited Lifetime Warranty</li>
			<li>Green LED Easily Indicates Protection</li>
			<li>Weather-Rated under NEMA 4X</li>
		</ul>',
				'post_excerpt'	=>	'Protect your home and HVAC equipment from damaging power fluctuations caused by electrical surges, brownouts, and other voltage disturbances.',
				'post_type'     =>	'products',
				'menu_order'  	=>  5000,
				'tax_input'		=>  array('product-brand'=>'rectorseal', 'product-type'=>'surge-protection', 'product-class'=>'best'),
				'meta_input'	=>	array('brochure'=>'https://rectorseal.bynder.com/m/6bb4f3b0889dcf4/original/RSH-Product-Catalog.pdf'),
				'image_name'	=>	'RSH-50-surge-protector.webp'
		),





		array ( 'post_title'	=>	'Rectorseal Voltage Range Monitor',
				'post_content' 	=>	'<span class="descriptionText">Protect residential and commercial HVAC equipment from damaging voltage fluctuations. This system continuously monitors incoming power and responds to dangerous over- or under-voltage conditions that can harm motors and circuit boards.</span>

		<p>Built for reliable, customizable voltage protection, this system features adjustable over/under-voltage cutoff and restart delays to help protect HVAC equipment from unstable power conditions. A large LCD display, LED diagnostic indicators, and field-programmable controls make setup and monitoring simple, while internal memory stores up to 300 voltage or power-loss events and retains settings even during an outage. Heavy-duty relays and a 60-amp capacity provide dependable protection for demanding HVAC applications. It is fully programmable with a 90V–300V cutoff range, stores up to 300 power events, and is suitable for single-phase 120/240V air conditioners, heat pumps, and mini-split systems.</p>

		<ul>
			<li>Over-Under Voltage Cutoff Delay can be adjusted from 0.5 to 60 Seconds</li>
			<li>Load Restore Delay adjustable from 1 to 600 Seconds 120/240 Single Phase, 60 Amp Double Pole Capacity Relays (handles loads 15-60 Amps)</li>
			<li>Magnetic Latching Relays for Maximum Reliability</li>
			<li>Large Easy to Read LCD Digital Display with Dual Red and Green LED Diagnostics Indicators</li>
			<li>Easy Set Up, Field Programmable, No Laptop or Programming Tool Required</li>
		</ul>',
				'post_excerpt'	=>	'Protect residential and commercial HVAC equipment from damaging voltage fluctuations. This system continuously monitors incoming power and responds to dangerous over- or under-voltage conditions that can harm motors and circuit boards.',
				'post_type'     =>	'products',
				'menu_order'  	=>  5000,
				'tax_input'		=>  array('product-brand'=>'rectorseal', 'product-type'=>'surge-protection', 'product-class'=>'best'),
				'meta_input'	=>	array('brochure'=>'https://rectorseal.bynder.com/m/6bb4f3b0889dcf4/original/RSH-Product-Catalog.pdf'),
				'image_name'	=>	'rectorseal-voltage-range-monitor.webp'
		),







		array ( 'post_title'	=>	'Rectorseal RSH-50 VRM KIT',
				'post_content' 	=>	'<span class="descriptionText">Protects equipment from electrical surges and voltage disturbances by utilizing safety thermal fusing and gas discharge tube surge protection technology, combined with the ability to monitor the line protecting equipment against over and under voltage conditions that can cause damage to circuit boards and motors.</span>

		<p>Built for reliable, customizable voltage protection, this system features adjustable over/under-voltage cutoff and restart delays to help protect HVAC equipment from unstable power conditions. A large LCD display, LED diagnostic indicators, and field-programmable controls make setup and monitoring simple, while internal memory stores up to 300 voltage or power-loss events and retains settings even during an outage. Heavy-duty relays and a 60-amp capacity provide dependable protection for demanding HVAC applications. It is fully programmable with a 90V–300V cutoff range, stores up to 300 power events, and is suitable for single-phase 120/240V air conditioners, heat pumps, and mini-split systems.</p>

		<ul>
			<li>RSH-50 Surge Protector and Voltage Range Monitor inside a NEMA 3R/IP66 enclosure</li>
			<li>Magnetic Latching Relays for Maximum Reliability</li>
			<li>Large Easy to Read LCD Digital Display with Dual Red and Green LED Diagnostics Indicators</li>
			<li>Easy Set Up, Field Programmable, No Laptop or Programming Tool Required</li>
		</ul>',
				'post_excerpt'	=>	'Protects equipment from electrical surges and voltage disturbances by utilizing safety thermal fusing and gas discharge tube surge protection technology.',
				'post_type'     =>	'products',
				'menu_order'  	=>  5000,
				'tax_input'		=>  array('product-brand'=>'rectorseal', 'product-type'=>'surge-protection', 'product-class'=>'best'),
				'meta_input'	=>	array('brochure'=>'https://rectorseal.bynder.com/m/6bb4f3b0889dcf4/original/RSH-Product-Catalog.pdf'),
				'image_name'	=>	'rectorseal-RSH-50-kit.webp'
		),







		array ( 'post_title'	=>	'Rectorseal RSH-50 VRMDC KIT',
				'post_content' 	=>	'<span class="descriptionText">Protects single phase equipment from electrical surges and voltage disturbances by utilizing safety thermal fusing and gas discharge tube surge protection technology, combined with the ability to monitor the line protecting equipment against over and under voltage conditions that can cause damage to circuit boards and motors.</span>

		<p>This all-in-one HVAC electrical protection system combines a 60-amp safety disconnect, surge protection, and programmable over/under-voltage monitoring to help safeguard sensitive circuit boards and motors. Designed for residential and commercial 120/240V air conditioners, heat pumps, and mini-splits, it provides adjustable voltage cutoff and restart delays, surge protection, and storage for up to 300 power events. A large LCD display, LED diagnostics, simple field programming, and a durable NEMA 3R enclosure provide convenient monitoring and dependable protection.</p>

		<ul>
			<li>RSH-50 Surge Protector, Voltage Range Monitor, and 60A Disconnect Breaker inside a NEMA 3R/IP66 enclosure</li>
			<li>Magnetic Latching Relays for Maximum Reliability</li>
			<li>Large Easy to Read LCD Digital Display with Dual Red and Green LED Diagnostics Indicators</li>
			<li>Easy Set Up, Field Programmable, No Laptop or Programming Tool Required</li>
		</ul>',
				'post_excerpt'	=>	'Protects single phase equipment from electrical surges and voltage disturbances by utilizing safety thermal fusing and gas discharge tube surge protection technology.',
				'post_type'     =>	'products',
				'menu_order'  	=>  5000,
				'tax_input'		=>  array('product-brand'=>'rectorseal', 'product-type'=>'surge-protection', 'product-class'=>'best'),
				'meta_input'	=>	array('brochure'=>'https://rectorseal.bynder.com/m/6bb4f3b0889dcf4/original/RSH-Product-Catalog.pdf'),
				'image_name'	=>	'rectorseal-RSH-50-VRMDC.webp'
		),

	);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>