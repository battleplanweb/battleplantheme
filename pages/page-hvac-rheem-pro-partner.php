<?php
/* Battle Plan Web Design - Rheem Pro Partner */

$printPage = '
	<h1>Rheem Pro Partner</h2>

	<img class="align-right size-quarter-s noFX rheem-pro-partner-logo" src="/wp-content/themes/battleplantheme/common/hvac-rheem/pro-partner-logo.webp" loading="lazy" alt="Rheem Pro Partner" width="320" height="183" style="aspect-ratio:320/183" >

	<p>[get-biz info="name"] is proud to be an authorized Rheem Pro Partner. In order to qualify as a Pro Partner, we have demonstrated that we are experts in our craft. As a family-run business, we provide an essential service to our customers and employ members of our community.</p>

	<p>Rheem chooses its most elite heating & cooling professionals to become Rheem Pro Partners. Pro Partners are eligible for additional knowledge, resources, opportunities and recognition.</p>

	<h2>Why Choose Pro Partner?</h2>

	<p>You can trust Pro Partners to provide a truly top-of-industry customer experience. Rheem evaluates Pro Partners yearly to ensure they are continually providing exceptional customer service and meeting the highest program standards. They are held accountable for the dependable, safe and satisfactory installation and servicing of high-performing Rheem products.</p>

	<img class="align-right noFX size-third-s" src="/wp-content/themes/battleplantheme/common/hvac-rheem/pro-partner-products.webp" loading="lazy" alt="Rheem Heating and Cooling Products" width="320" height="245" style="aspect-ratio:320/245" >

	<p>Every year, Pro Partners complete advanced technical and professional training, enabling them to continually provide you with the best service and advice on all Rheem technologies and solutions.</p>

	<p>Pro Partners can offer you a better value on Rheem equipment and installations than any other contractor, with exclusive Pro Partner financing options and promotional offers—so you can afford the solution that’s best for you.</p>

	<p>When you need an independent heating and cooling contractor you can trust, turn to the pros. For more information, call <b>[get-biz info="area-phone"]</b> to speak with your local Rheem Pro Partner today!</p>';

if ( $type == "teaser" ) :
	return do_shortcode('
		 [txt size="100"]
		  <h2>What is a Rheem Pro Partner?</h2>

		  <a href="/rheem-pro-partner/" aria-hidden="true" tabindex="-1"><img src="/wp-content/themes/battleplantheme/common/hvac-rheem/pro-partner-logo.webp" loading="lazy" alt="We are proud to be a Rheem Pro Partner." class="align-right size-quarter-s noFX rheem-pro-partner-logo" width="320" height="183" style="aspect-ratio:320/183" ></a>

		  <p>The Rheem Pro Partner program is made up of handpicked dealers who are ready to listen, evaluate and find solutions that work smarter for you and your home.</p>
		  [btn link="/rheem-pro-partner/" ada="about the Rheem Pro Partner program"]Learn More[/btn]
		 [/txt]
	');

else :
	return $printPage;
endif;
?>