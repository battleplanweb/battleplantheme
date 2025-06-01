<?php
/* Battle Plan Web Design - Terms & Conditions */

$num = 0;
$email = '[get-biz info="email"]';
$phone = '[get-biz info="phone-link"]';
$biz = '<b>[get-biz info="name"]</b>';

$printPage = '
	<h1>Terms & Conditions for '.$biz.'</h1>
	<p>Effective Date: January 1, 2025</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') AGREEMENT TO TERMS</strong></p>
	<p>These Terms & Conditions ("Agreement") govern your access to and use of the website located at [get-domain link="true"] (the "Website") and any services provided by '.$biz.' (the "Company"). By using this Website or any of our Services, you agree to be bound by this Agreement.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') DEFINITIONS</strong></p>
	<ul>
		<li><b>"Company," "We," "Us," or "Our"</b> refers to '.$biz.' and all associated staff, affiliates, or contractors.</li>
		<li><b>"You," "Your," "User," or "Client"</b> refers to the person using our Website or Services.</li>
		<li><b>"Parties"</b> refers collectively to both you and the Company.</li>
	</ul>';

$num++;
$printPage .= '
	<p><strong>'.$num.') ACCEPTANCE</strong></p>
	<p>If you do not agree to be bound by these Terms, you must stop using this Website immediately. Your continued use constitutes your ongoing acceptance of the Agreement.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') LICENSE TO USE</strong></p>
	<p>We grant you a limited, non-exclusive, non-transferable, and revocable license to access and use the Website and related content ("Company Materials") for personal or internal business use. This license terminates upon breach of this Agreement or cessation of use.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') INTELLECTUAL PROPERTY</strong></p>
	<p>All content on this Website, including text, images, logos, designs, code, and trademarks, is owned by the Company and protected under applicable IP laws.</p>
	<ul>
		<li>You agree not to reproduce, distribute, or exploit Company IP without written permission.</li>
		<li>By submitting any content to the Website ("Your Content"), you grant us a worldwide, royalty-free license to use, display, and distribute it to operate our business. We claim no ownership of Your Content.</li>
		<li>If you believe your IP rights are being violated, please contact us immediately.</li>
	</ul>';

$num++;
$printPage .= '
	<p><strong>'.$num.') USER ACCOUNTS</strong></p>
	<p>If you register an account, you are responsible for safeguarding login credentials and updating your information. You agree not to share your account or engage in fraud. We may terminate accounts for any abuse or violations.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') ACCEPTABLE USE</strong></p>
	<p>You agree not to use our Website or Services for unlawful or harmful activities. Prohibited actions include:</p>
	<ol type="a">
		<li>Harassment, abuse, or violation of others’ legal rights</li>
		<li>Intellectual property infringement</li>
		<li>Uploading viruses or harmful software</li>
		<li>Fraud or deceptive practices</li>
		<li>Illegal gambling, pyramid schemes, or obscene content</li>
		<li>Inciting hate, violence, or discrimination</li>
		<li>Collecting personal data without consent</li>
	</ol>';

$num++;
$printPage .= '
	<p><strong>'.$num.') AFFILIATE MARKETING & ADVERTISING</strong></p>
	<p>This Website may include affiliate links or sponsored content. We may receive commissions on purchases made through these links. We disclose this per FTC guidelines and applicable law.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') PRIVACY & DATA USE</strong></p>
	<p>We collect personal and passive data (e.g., cookies, usage logs) to operate and improve the Website. Your use implies consent to such practices. See our <a href="/privacy-policy">Privacy Policy</a> for details.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') ASSUMPTION OF RISK</strong></p>
	<p>Our Website and Services are provided "as-is." Any decisions made using information on the site are at your own risk. We do not provide legal, medical, or financial advice.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') SALES & THIRD-PARTY PRODUCTS</strong></p>
	<p>We strive for accuracy in product listings but do not guarantee reliability. You assume all risk for purchases made through us or any linked third parties. We are not liable for third-party products or services.</p>';

if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) :
	$num++;
	$printPage .= '
		<p><strong>'.$num.') SHIPPING, RETURNS, & PAYMENTS</strong></p>
		<p>All orders are subject to acceptance. Prices, availability, and delivery dates may change. We may issue refunds or request additional info. You agree to monitor your payment method. See our full return policy: [get-biz info="return-policy"]</p>';
endif;

$num++;
$printPage .= '
	<p><strong>'.$num.') SECURITY & REVERSE ENGINEERING</strong></p>
	<ul>
		<li>Do not reverse engineer or tamper with any software or code.</li>
		<li>Unauthorized access or data scraping is strictly prohibited.</li>
	</ul>';

$num++;
$printPage .= '
	<p><strong>'.$num.') DATA LOSS & LIABILITY</strong></p>
	<p>We are not responsible for loss of data or damage from using this Website or Services. Use at your own risk.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') INDEMNIFICATION</strong></p>
	<p>You agree to indemnify and hold us harmless from any claims, including attorney fees, resulting from your use of the Website or breach of this Agreement.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') SPAM POLICY</strong></p>
	<p>Mass commercial email, spam, or scraping user information is strictly prohibited.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') THIRD-PARTY LINKS</strong></p>
	<p>We are not responsible for content or damages arising from linked third-party sites or services.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') MODIFICATION OF TERMS</strong></p>
	<p>We reserve the right to update these Terms at any time. Continued use of the Website implies agreement to the latest version. Check this page periodically.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') TERMINATION</strong></p>
	<p>We may terminate your access to the Website or Services at our discretion, with or without cause. Some provisions (IP, indemnity, arbitration, etc.) will survive termination.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') NO WARRANTIES</strong></p>
	<p>This Website is provided "as is" with no warranties—express or implied—including fitness for a particular purpose, merchantability, or uninterrupted service.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') LIMITATION OF LIABILITY</strong></p>
	<p>To the fullest extent permitted by law, we are not liable for any damages resulting from your use of the Website or Services.</p>';

$num++;
$printPage .= '
	<p><strong>'.$num.') GENERAL PROVISIONS</strong></p>
	<ul>
		<li><b>Language:</b> All communications will be in English.</li>
		<li><b>Jurisdiction:</b> This Agreement is governed by the laws of [get-biz info="state-full"]. Venue for disputes shall lie exclusively within this jurisdiction.</li>
		<li><b>Arbitration:</b> Disputes must first attempt informal resolution, then binding arbitration under state/federal law. <em>Company’s IP claims are excluded and may be litigated.</em></li>
		<li><b>Severability:</b> Invalid parts will not affect the rest of the Agreement.</li>
		<li><b>No Waiver:</b> Failure to enforce any term is not a waiver of future rights.</li>
		<li><b>Headings:</b> Are for reference only.</li>
		<li><b>No Partnership:</b> This Agreement creates no agency, joint venture, or partnership.</li>
	</ul>';

$num++;
$printPage .= '
	<p><strong>'.$num.') CHANGES TO THESE TERMS</strong></p>
	<p>You are responsible for checking these Terms periodically. Changes are effective upon posting on this page.</p>';

return $printPage;
?>
