<?php
/* Battle Plan Web Design - Comfortmaker Elite Dealer */

$printPage = '
	<h1>Comfortmaker</h1>
	<h2>Elite Dealer</h2>

	<img class="alignright size-quarter-s noFX comfortmaker-elite-dealer-logo" src="/wp-content/themes/battleplantheme/common/hvac-comfortmaker/elite-dealer-logo.png" loading="lazy" alt="Comfortmaker Elite Dealer" width="258" height="258" style="aspect-ratio:258/258" />

	<p>[get-biz info="name"] is proud to be an authorized Comfortmaker Elite Dealer.  That means we offer best-of-the-best installation, maintenance and customer service, the perfect complement to your Comfortmaker® heating and cooling system.</p>

	<p>As a Comfortmaker Elite Dealer, we have the knowledge, experience, and training to help your system achieve peak performance. When you call us, you can rest assured we will provide:</p>
	
	<ul>
    	<li><b>Expertise</b> – You can relax knowing your Elite Dealer was selected as one of the best Comfortmaker dealers in their region.</li>

    	<li><b>Product Knowledge</b> - Elite Dealers offer expertise on which products best meet your needs and are up-to-date with the latest in comfort technology and training.</li>

    	<li><b>Payment Options</b> – Elite Dealers offer financing options, upon credit approval, to pay for your system with monthly installments instead of the full price at purchase.</li>

    	<li><b>Peace of Mind</b> – Elite Dealers can offer two additional years of No Hassle Replacement™ limited warrantycoverage on qualifying products, in addition to our standard 10-year parts limited warranty* for your continued comfort. </li>
	</ul>

	<p>For more information, call <b>[get-biz info="area-phone"]</b> to speak with your local Comfortmaker Elite Dealer today!</p>';

if ( $type == "teaser" ) :
	return do_shortcode('
		 [txt size="100"]
		  <h2>What is a Comfortmaker Elite Dealer?</h2>

		  <a href="/comfort-maker-elite-dealer/" aria-hidden="true" tabindex="-1"><img src="/wp-content/themes/battleplantheme/common/hvac-comfortmaker/elite-dealer-logo.png" loading="lazy" alt="We are a Comfortmaker Elite Dealer." class="alignright size-quarter-s noFX comfortmaker-elite-dealer-logo" width="258" height="258" style="aspect-ratio:258/258" /></a>

		  <p>To ensure dependable, lasting comfort, you want to select a dealer that offers best-of-the-best installation, maintenance and customer service.</p>
		  [btn link="/comfort-maker-elite-dealer/" ada="about the Elite Dealer program"]Learn More[/btn]
		 [/txt]
	');	

else : 
	return $printPage;	
endif;
?>