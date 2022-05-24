<?php
/* Battle Plan Web Design - Tempstar Elite Dealer */

$printPage = '
	<h1>Tempstar</h1>
	<h2>Elite Dealer</h2>

	<img class="alignright size-quarter-s noFX tempstar-elite-dealer-logo" src="/wp-content/themes/battleplantheme/common/hvac-tempstar/tempstar-elite-dealer-logo.png" loading="lazy" alt="Tempstar Elite Dealer" width="258" height="258" style="aspect-ratio:258/258" />

	<p>[get-biz info="name"] is proud to be an authorized Tempstar Elite Dealer.  That means we offer best-of-the-best installation, maintenance and customer service, the perfect complement to your Tempstar® heating and cooling system.</p>

	<p>As a Tempstar Elite Dealer, we have the knowledge, experience, and training to help your system achieve peak performance. When you call us, you can rest assured we will provide:</p>	
	
	<ul>
    	<li><b>Expertise</b> – You can relax knowing your Elite Dealer was selected as one of the best Tempstar dealers in their region.</li>

    	<li><b>Product Knowledge</b> - Elite Dealers offer expertise on which products best meet your needs and are up-to-date with the latest in comfort technology and training.</li>

		<img class="alignright noFX size-third-s" src="/wp-content/themes/battleplantheme/common/hvac-tempstar/tempstar-products.png" loading="lazy" alt="American Standard Heating and Cooling Products" width="320" height="280" style="aspect-ratio:320/280" />

    	<li><b>Payment Options</b> – Elite Dealers offer financing options, upon credit approval, to pay for your system with monthly installments instead of the full price at purchase.</li>

    	<li><b>Peace of Mind</b> – Elite Dealers can offer two additional years of No Hassle Replacement™ limited warrantycoverage on qualifying products, in addition to our standard 10-year parts limited warranty* for your continued comfort. </li>
	</ul>

	<p>For more information, call <b>[get-biz info="area-phone"]</b> to speak with your local Tempstar Elite Dealer today!</p>';

if ( $type == "teaser" ) :
	return do_shortcode('
		 [txt size="100"]
		  <h2>What is a Tempstar Elite Dealer?</h2>

		  <a href="/tempstar-elite-dealer/" aria-hidden="true" tabindex="-1"><img src="/wp-content/themes/battleplantheme/common/hvac-tempstar/tempstar-elite-dealer-logo.png" loading="lazy" alt="We are a Tempstar Elite Dealer." class="alignright size-quarter-s noFX tempstar-elite-dealer-logo" width="258" height="258" style="aspect-ratio:258/258" /></a>

		  <p>To ensure dependable, lasting comfort, you want to select a dealer that offers best-of-the-best installation, maintenance and customer service.</p>
		  [btn link="/tempstar-elite-dealer/" ada="about the Elite Dealer program"]Learn More[/btn]
		 [/txt]
	');	

else : 
	return $printPage;	
endif;
?>