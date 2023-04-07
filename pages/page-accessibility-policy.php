<?php
/* Battle Plan Web Design - Accessibility Policy */

$printPage = '
	<h1>Website Accessibility Policy</h1>

	<p>This Accessibility Policy is effective as of April 4, 2023.</p>

	<p>[get-biz info="name"] strives to make our website as accessible and usable as possible. We do this by following Section 508 and the Web Content Accessibility Guidelines (WCAG 2.0) produced by the World Wide Web Consortium (W3C, the web\'s governing body).</p>

	<p>Section 508 is a legal requirement and WCAG is a set of checkpoints and guidelines that help ensure that websites are designed and written properly.</p>

	<p>For those familiar with Section 508 and WCAG, we aim for AA compliance across our site. We also look for opportunities to meet AAA compliance.</p>
		
	<h2>Additional Help</h2>

	<p>If you have any type of disability, we recommend that you visit the <a href="https://www.fcc.gov/general/accessibility-clearinghouse-0">FCC Accessibility Clearinghouse</a> and the <a href="https://www.access-board.gov/ict/#additional-resources">Access Board</a> websites. You will find expert advice such as alternative screen readers, screen magnifiers and other devices that can make using a computer easier and more enjoyable.</p>

	<p>We also recommend that you visit AbilityNet\'s <a href="https://mcmw.abilitynet.org.uk/">My Computer My Way</a>, which provides advice on making your computer accessible.</p>
	
	<h2>Feedback</h2>

	<p>If you have a problem using our site, please contact us and provide the URL (web address) of the material you tried to access, the problem you experienced, and your contact information. We will attempt to provide the information you\'re seeking.</p>';
	
if ( $contact == "true" ) : 	
	$printPage .= '	
	 <p>Our email address is <a href="mailto:'.$email.'">'.$email.'</a> or you can visit our <a href="/contact">Contact</a> page.</p>';	
endif;

return $printPage;	
?>