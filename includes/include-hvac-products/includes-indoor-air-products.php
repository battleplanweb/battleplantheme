<?php
/* Battle Plan Web Design - Add & Remove Indoor Air Quality Products */

/*  ADD TO FUNCTIONS-SITE
if ( get_option('bp_product_upload_2022_08_11') != "completed" ) :
	add_action( 'wp_loaded', 'add_indoor_air_products', 10 );
	function add_indoor_air_products() {
		$brand = "iaq"; // lowercase
		$productImgAlt = "Indoor Air Quality Product";

		$addProducts = array (
			***copy/paste: the arrays you want to include
		);
		require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
		updateOption( 'bp_product_upload_2022_08_11', 'completed', false );
	}
endif;
*/

/* PRODUCT OVERVIEW
[product-overview type="indoor air quality"]
*/

add_action( 'wp_loaded', 'add_indoor_air_products', 10 );
function add_indoor_air_products() {

	$brand = "iaq"; // lowercase
	$productImgAlt = "Indoor Air Quality Product";

	//$removeImages = array('Amana-AH-01.jpg', 'Amana-F-02.jpg', 'Amana-F-01.jpg', 'Amana-02.jpg', 'Amana-01.jpg');

	//$removeProducts = array('asxv9-air-conditioner', 'asxc7-air-conditioner', 'asxh5-air-conditioner', 'asxh4-air-conditioner', 'aszv9-heat-pump', 'aszc7-heat-pump', 'aszh5-heat-pump', 'aszh4-heat-pump', 'amvc96-90-afue-gas-furnace', 'amvc8-advc8-80-afue-gas-furnace', 'am9s96-u-90-afue-gas-furnace', 'am9s80-80-afue-gas-furnace', 'amve-air-handler', 'ahve-air-handler', 'amst-air-handler' );

	$addProducts = array (

		array ( 'post_title'	=>	'Rectorseal Active Gold Purifier',
				'post_content' 	=>	'<span class="descriptionText">The Dust Free Active Air purifier\'s unique, dual technologies target air quality problems at the source using germicidal UV‑C and ionization systems to clean the air in your home.</span>

		<p>The UV‑C light and ionization provide the comprehensive IAQ solution. Laboratory tests have also proven that the Dust Free Active can reduce airborne bacteria. Designed to be installed into existing HVAC systems to improve indoor air quality. Suitable for residential and commercial applications, providing effective control of airborne particulates, odors, and germs through advanced filtration technologies.</p>

		<ul>
			<li>Dual active air purification technologies</li>
			<li>Reduces airborne particulate matter by improving the performance of your existing filtration system using millions of charged ions</li>
			<li>Effective against odors, VOC, and inactivates virus in the air</li>
			<li>Redesigned ionizers produce more negatively‑charged ions and increase ion density</li>
		</ul>',
				'post_excerpt'	=>	'The Dust Free Active Air purifier\'s unique, dual technologies target air quality problems at the source using germicidal UV‑C and ionization systems to clean the air in your home.',
				'post_type'     =>	'products',
				'menu_order'  	=>  2000,
				'tax_input'		=>  array('product-brand'=>'rectorseal', 'product-type'=>'indoor-air-quality', 'product-class'=>'best'),
				'meta_input'	=>	array('brochure'=>'https://rectorseal.bynder.com/asset/0c188c77-33b1-4e65-8e25-f03985f0992f/DustFree-IAQ-CAT-R50846-0325-WEB-pdf.pdf'),
				'image_name'	=>	'rectorseal-active-gold-purifier.webp'
		),






		array ( 'post_title'	=>	'Rectorseal Dust Free Lightstick Gold',
				'post_content' 	=>	'<span class="descriptionText">The Dust Free Lightstick Gold delivers optimized germicidal UV performance, while also ensuring maximum safety compliance with unmatched operational stability. The Lightstick Gold sets the new standard for microbial inactivation and HVAC surface disinfection.</span>

		<p>Designed for safe, reliable, and efficient UV operation, this system features an enhanced power cord with a built-in safety interlock, ozone-free performance, and FCC-compliant protection against electronic interference. A pre-heated UV lamp, surge-protected ballast, improved heat tolerance, and power factor correction support long-lasting, energy-efficient operation, while a built-in lamp failure indicator makes maintenance easier.
		</p>

		<ul>
			<li><b>Enhanced Safety:</b> New cord design integrates power and safety interlock for improved maintenance safety.</li>
			<li><b>Ozone Free and Compliant:</b> New cord design integrates power and safety interlock for improved maintenance safety.</li>
			<li><b>Reduced Interference:</b> FCC Part 15B EMI compliance ensures seamless, noise-free operation.</li>
			<li><b>Reliable and Durable:</b> Features expanded range ballast with 3000V surge protection, pre-heated UV lamp for extended life, and superior heat tolerance.</li>
			<li><b>Efficient and Stable Power:</b> Incorporates Power Factor Correction for reduced power consumption and a built-in lamp failure indicator for proactive maintenance.</li>
		</ul>',
				'post_excerpt'	=>	'The Dust Free Lightstick Gold delivers optimized germicidal UV performance, while also ensuring maximum safety compliance with unmatched operational stability.',
				'post_type'     =>	'products',
				'menu_order'  	=>  2000,
				'tax_input'		=>  array('product-brand'=>'rectorseal', 'product-type'=>'indoor-air-quality', 'product-class'=>'best'),
				'meta_input'	=>	array('brochure'=>'https://rectorseal.bynder.com/asset/75d9916a-2746-457a-8b72-5bca9129d788/Dust-Free-LightstickGold-Germicidal-PDS-R51180-1024-pdf.pdf'),
				'image_name'	=>	'rectorseal-dust-free-lighstick-gold.webp'
		),






		array ( 'post_title'	=>	'Air Scrubber by ActivePure A1013U',
				'post_content' 	=>	'<span class="descriptionText">The Air Scrubber by ActivePure attaches directly to the HVAC system ductwork and utilizes ActivePure Technology to reduce contaminants in the air in indoor spaces while the HVAC fan is running.</span>

		<p>The A1013V Ozone-Free air purification system uses ActivePure® Technology with a 9-inch cell and integrates directly with the air handler to help treat indoor air throughout the home. Designed for spaces up to 2,000 sq. ft. nominally and 3,000 sq. ft. maximum, it is CARB Certified, validated to UL 2998 for ozone emissions, and conforms to UL 1598 safety standards. The system includes a safety switch and is backed by a 5-year limited warranty, with a 2-year warranty on the ActivePure Cell Assembly.</p>

		<ul>
			<li>Reduces VOC gases, smoke, and odors in the air without the use of ozone</li>
			<li>Protects your heating and cooling system from potential buildup</li>
			<li>Discreet or concealed installation</li>
			<li>Improved patented ActivePure Cell</li>
		</ul>',
				'post_excerpt'	=>	'The Air Scrubber by ActivePure attaches directly to the HVAC system ductwork and utilizes ActivePure Technology to reduce contaminants in the air in indoor spaces while the HVAC fan is running.',
				'post_type'     =>	'products',
				'menu_order'  	=>  2000,
				'tax_input'		=>  array('product-brand'=>'activepure', 'product-type'=>'indoor-air-quality', 'product-class'=>'best'),
				'meta_input'	=>	array('brochure'=>''),
				'image_name'	=>	'activepure-air-scrubber-a1013u.webp'
		),

	);

	require_once get_template_directory().'/includes/include-hvac-products/includes-product-uploader.php';
}
?>