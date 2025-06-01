<?php
/* Battle Plan Web Design - Condensed Privacy Policy */

$num = 0;
$biz = '<b>[get-biz info="name"]</b>';
$email = '[get-biz info="email"]';
$phone = '[get-biz info="phone-link"]';
$state = '[get-biz info="state-full"]';

$printPage = '
	<h1>Privacy Policy for '.$biz.'</h1>

	<p>Effective Date: January 1, 2025</p>

	<p>Welcome to '.$biz.'. Your privacy is important to us. This Privacy Policy outlines what personal information we collect, how we use it, and your rights regarding that information. By using this website, you agree to the terms of this policy.</p>

	<p>The terms “we,” “us,” and “our” refer to '.$biz.'; “you” refers to users of this site. “Personal information” means any data you voluntarily submit that identifies you, such as your name, phone number, or email address.</p>';
	
$num++;
$printPage .= '
	<p><strong>'.$num.') INFORMATION WE COLLECT</strong></p>

	<ul>
		<li><b>Contact Information:</b> Name, email, phone, company, and location when you fill out a form or request service.</li>
		<li><b>Marketing Opt-ins:</b> Name and email/phone when subscribing to newsletters or SMS alerts.</li>
		<li><b>Usage Data:</b> Information such as IP address, browser type, pages visited, referral sources, and timestamps via tools like Google Analytics.</li>
	</ul>';

$num++;
$printPage .= '
	<p><strong>'.$num.') HOW WE USE YOUR INFORMATION</strong></p>

	<p>Your information is used to respond to inquiries, schedule services, send invoices, deliver updates via email or SMS, and improve our site. Data is shared only with trusted third-party providers helping us operate our business. We do <b>not</b> sell your personal information.</p>

	<p>If you opt into communications, we’ll use your contact details solely for that purpose. You may opt out anytime by:</p>
	<ul>
		<li>Replying STOP to text messages</li>
		<li>Clicking "Unsubscribe" in emails</li>';
		if ( $phone !== '' ) $printPage .= '<li>Calling us at '.$phone.'</li>';
		if ( $email !== '' ) $printPage .= '<li>Emailing us at <a href="mailto:'.$email.'">'.$email.'</a></li>';		
	$printPage .= '</ul>';

$num++;
$printPage .= '
	<p><strong>'.$num.') COOKIES & TRACKING</strong></p>

	<p>We use cookies to improve functionality and personalize your experience. Cookies are small text files stored on your device. Some expire when you close your browser; others remain until deleted. You can disable cookies in your browser settings, though this may impact site functionality. This site does not currently respond to Do Not Track signals.</p>

	<p>We also use Google Analytics to understand visitor behavior. Google may use collected data to personalize ads on its network. Learn more at <a href="https://policies.google.com/technologies/partner-sites" target="_blank">Google’s Privacy Page</a>.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') THIRD-PARTY LINKS</strong></p>

	<p>Our site may contain links to third-party websites. This Privacy Policy only applies to our website. We are not responsible for the content or privacy practices of other websites.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') DATA SECURITY</strong></p>

	<p>We take precautions to protect your data from unauthorized access or misuse. However, no internet transmission is ever 100% secure. By using this site, you acknowledge the inherent risks and release us from liability for intercepted or misused data.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') CHILDREN</strong></p>

	<p>This website is intended for users 18 and older. We do not knowingly collect or store personal information from children under 18.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') CHANGES TO THIS POLICY</strong></p>

	<p>This Privacy Policy may be updated from time to time. It is your responsibility to review this page for changes. Continued use of the site indicates your acceptance of the current version.</p>';


if ( $state !== '' ) {
	$num++;
	$printPage .= '
		<p><strong>'.$num.') GOVERNING LAW</strong></p>
		<p>This Privacy Policy shall be governed by the laws of the State of '.$state.', without regard to its conflict of law principles.</p>';
}

$num++;
$printPage .= '
	<p><strong>'.$num.') CONTACT</strong></p>

	<p>Questions? Contact us via one of the following methods: </p>

	<ul>';
		if ( $phone !== '' ) $printPage .= '<li>Call us at '.$phone.'</li>';
		if ( $email !== '' ) $printPage .= '<li>Email us at <a href="mailto:'.$email.'">'.$email.'</a></li>';
		$printPage .= '<li>Use our <a href="/contact">Contact</a> page.</li>
	</ul>';

return $printPage;
?>
