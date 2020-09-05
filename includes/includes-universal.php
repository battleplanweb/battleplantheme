<?php
/* Battle Plan Web Design Universal Includes
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Coupon
# Privacy Policy

--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Coupon
--------------------------------------------------------------*/
add_shortcode( 'coupon', 'battleplan_coupon' );
function battleplan_coupon( $atts, $content = null ) {
	$a = shortcode_atts( array( 'action'=>'Mention Our Website For', 'discount'=>'$20 OFF', 'service'=>'Service Call', 'disclaimer'=>'First time customers only.  Limited time offer.  Not valid with any other offer.  Must mention coupon at time of appointment.  During regular business hours only.  Limit one coupon per system.' ), $atts );
	$action = esc_attr($a['action']);
	$discount = esc_attr($a['discount']);
	$service = esc_attr($a['service']);
	$disclaimer = esc_attr($a['disclaimer']);
	
	return do_shortcode('
		[txt class="coupon"]
			<h2 class="action">'.$action.'</h2>
			<h2 class="discount">'.$discount.'</h2>
			<h2 class="service">'.$service.'</h2>
			<p class="disclaimer">'.$disclaimer.'</p>	
		[/txt]
	');
}


/*--------------------------------------------------------------
# Privacy Policy
--------------------------------------------------------------*/
add_shortcode( 'privacy-policy', 'battleplan_privacy_policy' );
function battleplan_privacy_policy( $atts, $content = null ) {
	$a = shortcode_atts( array( 'contact'=>'true', ), $atts );
	$contact = esc_attr($a['contact']);
	$email = do_shortcode("[get-biz info='email']");
	if ( !$email ) $contact = 'false';
	
	$printPolicy = '
		<h1>Website Privacy Policy</h1>

		<p>This Privacy Policy is effective as of January 1, 2019.</p>

		<p>[get-biz info="name"] is committed to protecting your privacy online. This Privacy Policy describes the personal information we collect through this website at [get-domain link="true"] (the “Site”), and how we collect and use that information.</p>

		<p>The terms “we,” “us,” and “our” refers to [get-biz info="name"]. The terms “user,” “you,” and “your” refer to site visitors, customers, and any other users of the site.</p>

		<p>The term “personal information” is defined as information that you voluntarily provide to us that personally identifies you and/or your contact information, such as your name, phone number, and email address.</p>

		<p>Use of this website, including all materials presented herein and all online services provided by [get-biz info="name"], is subject to the following Privacy Policy. This Privacy Policy applies to all site visitors, customers, and all other users of the site. By using the Site or Service, you agree to this Privacy Policy, without modification, and acknowledge reading it.</p>

		<h2>Information We Collect</h2>

		<p>This Site only collects the personal information you voluntarily provide to us, which may include:</p>

		<ul>
			<li>Email address and contact information (name, number, company, location) in order to allow customers to request our services or ask us questions;</li>
			<li>First and last names and email address in order to subscribe visitors to our newsletter;</li>
			<li>As listed below in Activity we collect user data with Google Analytics in order to better understand how to improve our Service, and user experience.</li>
		</ul>

		<p>The information you provide is used to process transactions, book service calls, answer questions, send periodic emails, and improve the service we provide. We do share your information with trusted third parties who assist us in operating our website, conducting our business and servicing clients and visitors. These trusted third parties agree to keep this information confidential. Your personal information will never be shared with unrelated third parties.</p>

		<h2>Activity</h2>

		<p>We may record information relating to your use of the Site, such as the searches you undertake, the pages you view, your browser type, IP address, requested URL, referring URL, and timestamp information. We use this type of information to administer the Site and provide the highest possible level of service to you. We also use this information in the aggregate to perform statistical analyses of user behavior and characteristics in order to measure interest in and use of the various areas of the Site.</p>

		<p>Along these activities we utilize Google Analytics, a web analysis service provided by Google. Google utilizes the data collected to track and examine the use of [get-domain], to prepare reports on its activities and share them with other Google services. Google may use the data collected to contextualize and personalize the ads of its own advertising network.</p>

		<h2>Cookies</h2>

		<p>We may send cookies to your computer in order to uniquely identify your browser and improve the quality of our service. The term “cookies” refers to small pieces of information that a website sends to your computer’s hard drive while you are viewing the Site. We may use both session cookies (which expire once you close your browser) and persistent cookies (which stay on your computer until you delete them). You have the ability to accept or decline cookies using your web browser settings. If you choose to disable cookies, some areas of the Site may not work properly or at all.  The Site does not respond to Do Not Track signals sent by your browser.</p>

		<h2>Third Party Links</h2>

		<p>The Site may contain links to third party websites. Except as otherwise discussed in this Privacy Policy, this document only addresses the use and disclosure of information we collect from you on our Site. Other sites accessible through our site via links or otherwise have their own policies in regard to privacy. We are not responsible for the privacy policies or practices of third parties.</p>

		<h2>Security</h2>

		<p>We maintain security measures to protect your personal information from unauthorized access, misuse, or disclosure. However, no exchange of data over the Internet can be guaranteed as 100% secure. While we make every effort to protect your personal information shared with us through our Site, you acknowledge that the personal information you voluntarily share with us through this Site could be accessed or tampered with by a third party. You agree that we are not responsible for any intercepted information shared through our Site without our knowledge or permission. Additionally, you release us from any and all claims arising out of or related to the use of such intercepted information in any unauthorized manner.</p>

		<h2>Children</h2>

		<p>To access or use the Site, you must be 18 years old or older and have the requisite power and authority to enter into this Privacy Policy. Children under the age of 18 are prohibited from using the Site.</p>';
	
	if ( $contact == "true" ) : $printPolicy .= '
		<h2>Updating Your Information</h2>

		<p>You may access and correct your personal information and privacy preferences by contacting us via email at <a href="mailto:'.$email.'">'.$email.'</a>.</p>';
	endif;

	$printPolicy .= '
		<h2>Changes To This Policy</h2>

		<p>You acknowledge and agree that it is your responsibility to review this Site and this Policy periodically and to be aware of any modifications. We will notify you of any changes to this privacy policy by posting those changes on this page.</p>';

	if ( $contact == "true" ) : $printPolicy .= '
		<h2>Contact</h2>

		<p>If you have questions about our privacy policy, please email us at <a href="mailto:'.$email.'">'.$email.'</a> or visit our <a href="/contact">Contact</a> page.</p>';	
	endif;

	return do_shortcode($printPolicy);	
}	





	
?>