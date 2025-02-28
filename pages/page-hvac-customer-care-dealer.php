<?php
/* Battle Plan Web Design - Customer Care Dealer */

$printPage = '
	<h1>American Standard</h1>
	<h2>Customer Care Dealer</h2>

	<img class="align-right size-quarter-s noFX customer-care-logo" src="/wp-content/themes/battleplantheme/common/hvac-american-standard/customer-care-dealer-logo.webp" loading="lazy" alt="American Standard Customer Care Dealer" width="258" height="258" style="aspect-ratio:258/258" />

	<p>[get-biz info="name"] is proud to be an authorized American Standard Customer Care Dealer.</p>

	<p>American Standard Heating &amp; Air Conditioning is the industry leader in HVAC systems. Known for reliability, sustainability, air quality and customer care, they take great pride in providing top-notch customer service.</p>

	<p>The Customer Care program is made up of handpicked dealers who are ready to listen, evaluate and find solutions that work smarter for you and your home.</p>

	<h2>Customer Care Dealer Advantage</h2>

	<p>Not just any HVAC representative can wear the American Standard Customer Care Dealer badge. This recognition must be proven through a commitment to service and properly trained staff. If you are working with a Customer Care Dealer, you can feel confident knowing they have a commitment to the following:</p>

	<img class="align-right noFX size-third-s" src="/wp-content/themes/battleplantheme/common/hvac-american-standard/customer-care-dealer-products.webp" loading="lazy" alt="American Standard Heating and Cooling Products" width="324" height="239" style="aspect-ratio:324/239" />

	<ul>
		<li><b>Product Knowledge Experts:</b> Customer Care Dealers are up-to-date on the latest technology and products, so they are able to provide recommendations for maximum efficiency and comfort to best meet your needs. Staff training is a top priority.</li>
		<li><b>Commitment to Customer Service:</b> Providing excellent customer service is critical, and Customer Care Specialists are committed to listening to your needs and responding quickly and appropriately. Efficient Climate guarantee a 100% customer satisfaction.</li>
		<li><b>Seeking Feedback:</b> The Customer Care program prides itself on providing great customer experiences, and are always looking for ways to improve. It welcomes feedback, and provides a homeowner satisfaction survey to learn and maintain this satisfaction.</li>
	</ul>

	<p>For more information, call <b>[get-biz info="area-phone"]</b> to speak with your local American Standard Customer Care Dealer today!</p>';

if ( $type == "teaser" ) :
	return do_shortcode('
		 [txt size="100"]
		  <h2>What is a Customer Care Dealer?</h2>

		  <a href="/customer-care-dealer/" aria-hidden="true" tabindex="-1"><img src="/wp-content/themes/battleplantheme/common/hvac-american-standard/customer-care-dealer-logo.webp" loading="lazy" alt="We are an American Standard Customer Care Dealer." class="align-right size-quarter-s noFX customer-care-logo" width="258" height="258" style="aspect-ratio:258/258" /></a>

		  <p>The Customer Care program is made up of handpicked dealers who are ready to listen, evaluate and find solutions that work smarter for you and your home.</p>
		  [btn link="/customer-care-dealer/" ada="about the Customer Care program"]Learn More[/btn]
		 [/txt]
	');	

else : 
	return $printPage;	
endif;
?>