<?php
/* Battle Plan Web Design - York Certified Comfort Expert */

$printPage = '
	<h1>York</h1>
	<h2>Certified Comfort Expert</h2>

	<img class="align-right size-quarter-s noFX york-cert-comfort-expert-logo" src="/wp-content/themes/battleplantheme/common/hvac-york/cert-comfort-expert-logo.webp" loading="lazy" alt="York Certified Comfort Expert" width="500" height="168" style="aspect-ratio:500/168" >

	<p>[get-biz info="name"] is proud to be a York Certified Comfort Expert, which is a designation that assures you are working with the best in the business.</p>

	<p>We have the knowledge, experience, and training to help your system achieve peak performance.  As a York CCE, we:</p>

	<img class="align-right noFX size-third-s" src="/wp-content/themes/battleplantheme/common/hvac-york/york-product-family.webp" loading="lazy" alt="York Heating and Cooling Products" width="500" height="339" style="aspect-ratio:500/339" >

	<ul>
    	<li>Participate in ongoing training regarding the latest developments in product design and energy efficiency</li>

    	<li>Install only premium-quality equipment</li>

    	<li>Have proven success in creating and maintaining home comfort</li>
	</ul>

	<p>Plus, as a CCE contractor, we know that purchasing a new HVAC system is a big decision. That\'s why we are experts when it comes to offering advice on York equipment and how to get the best performance out of your home comfort system.</p>

	<p>For more information, call <b>[get-biz info="area-phone"]</b> to speak with your local York Certified Comfort Expert today!</p>';

if ( $type == "teaser" ) :
	return do_shortcode('
		 [txt size="100"]
		  <h2>What is a York Certified Comfort Expert?</h2>

		  <a href="/york-certified-comfort-expert/" aria-hidden="true" tabindex="-1"><img src="/wp-content/themes/battleplantheme/common/hvac-york/cert-comfort-expert-logo.webp" loading="lazy" alt="We are a York Certified Comfort Expert." class="align-right size-quarter-s noFX york-cert-comfort-expert-logo" width="500" height="168" style="aspect-ratio:500/168" ></a>

		  <p>To ensure dependable, lasting comfort, you want to select a dealer that offers best-of-the-best installation, maintenance and customer service.</p>
		  [btn link="/york-certified-comfort-expert/" ada="about the York Certified Comfort Expert program"]Learn More[/btn]
		 [/txt]
	');

else :
	return $printPage;
endif;
?>