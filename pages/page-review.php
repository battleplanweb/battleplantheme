<?php
/* Battle Plan Web Design - Review Questions & Redirect */

$printPage = '
	<h1>We Appreciate Your Business!</h1>	
	<p>We hope you were 100% satisfied with your experience at <b>[get-biz info="name"]</b>.  We would love it if you took just a moment to give us an honest review of our business.</p>
	<p>Click the platform below that you prefer, and you will be directed to the appropriate page. Thank you!';
	
if ( do_shortcode('[get-biz info="pid"]') != '' || do_shortcode('[get-biz info="google-review"]') != '' ) $printPage .= '[btn link="/google" new-tab="true"]Gmail Users[/btn][clear height="20px"]';
if ( do_shortcode('[get-biz info="facebook"]') != '' ) $printPage .= '[btn link="/facebook" new-tab="true"]Facebook Users[/btn][clear height="20px"]';
if ( do_shortcode('[get-biz info="yelp"]') != '' ) $printPage .= '[btn link="/yelp" new-tab="true"]Yelp Users[/btn]';
	
return $printPage;	
?>