<?php
/* Battle Plan Web Design - Ruud Pro Partner */

$printPage = '
	<h1>Ruud Pro Partner</h2>

	<img class="align-right size-quarter-s noFX ruud-pro-partner-logo" src="/wp-content/themes/battleplantheme/common/hvac-ruud/pro-partner-logo.webp" loading="lazy" alt="Ruud Pro Partner" width="320" height="183" style="aspect-ratio:320/183" >

	<p>[get-biz info="name"] is proud to be an authorized Ruud Pro Partner. No other contractor supplies, installs and services more Ruud residential solutions. Plus, Pro Partners are held to the highest standards of customer service, professional training and industry expertise.</p>

	<p>And you don’t have to take our word for it. All Pro Partners maintain a minimum 4-star customer rating each year, based on Ruud-validated online reviews from real homeowners like you.</p>

	<h2>Why Choose Pro Partner?</h2>

	<p>You can trust Pro Partners to provide a truly top-of-industry customer experience. Ruud evaluates Pro Partners yearly to ensure they are continually providing exceptional customer service and meeting the highest program standards. They are held accountable for the dependable, safe and satisfactory installation and servicing of high-performing Ruud products.</p>

	<img class="align-right noFX size-third-s" src="/wp-content/themes/battleplantheme/common/hvac-ruud/pro-partner-products.webp" loading="lazy" alt="Ruud Heating and Cooling Products" width="320" height="245" style="aspect-ratio:320/245" >

	<p>Every year, Pro Partners complete advanced technical and professional training, enabling them to continually provide you with the best service and advice on all Ruud technologies and solutions.</p>

	<p>Pro Partners can offer you a better value on Ruud equipment and installations than any other contractor, with exclusive Pro Partner financing options and promotional offers—so you can afford the solution that’s best for you.</p>

	<p>When you need an independent heating and cooling contractor you can trust, turn to the pros. For more information, call <b>[get-biz info="area-phone"]</b> to speak with your local Ruud Pro Partner today!</p>';

if ( $type == "teaser" ) :
	return do_shortcode('
		 [txt size="100"]
		  <h2>What is a Ruud Pro Partner?</h2>

		  <a href="/ruud-pro-partner/" aria-hidden="true" tabindex="-1"><img src="/wp-content/themes/battleplantheme/common/hvac-ruud/pro-partner-logo.webp" loading="lazy" alt="We are proud to be a Ruud Pro Partner." class="align-right size-quarter-s noFX ruud-pro-partner-logo" width="320" height="183" style="aspect-ratio:320/183" ></a>

		  <p>The Ruud Pro Partner program is made up of handpicked dealers who are ready to listen, evaluate and find solutions that work smarter for you and your home.</p>
		  [btn link="/ruud-pro-partner/" ada="about the Ruud Pro Partner program"]Learn More[/btn]
		 [/txt]
	');

else :
	return $printPage;
endif;
?>