<?php
/* Battle Plan Web Design - Review Questions & Redirect */

$printPage = '
	<h1>We Appreciate Your Business!</h1>	
	<p>We hope you were 100% satisfied with your experience at <b>[get-biz info="name"]</b>.  We would love it if you took just a moment to give us an honest review of our business.</p>	
	<p>Please answer the following, and wait for us to re-direct you to the appropriate platform.  Thank you!</p>
';
if ( do_shortcode('[get-biz info="pid"]') != '' ) :
	$printPage .= '	
		<div class="review-form">
		 <div class="question">Do you use Gmail?</div>
		 <button id="gmail-yes">Yes</button>
		 <button id="gmail-no">No</button>
		</div>
	';
endif;
if ( do_shortcode('[get-biz info="facebook"]') != '' ) :
	$printPage .= '	
		<div class="review-form">
		 <div class="question">Do you use Facebook?</div>
		 <button id="facebook-yes">Yes</button>
		 <button id="facebook-no">No</button>
		</div>
	';
endif;	
$printPage .= '		
	<div class="review-form">
	 <p>Please use this form to submit your review.  Thank you!</p>
	 [contact-form-7 title="Contact Us Form"]
	</div>
';	
	
return $printPage;	
?>