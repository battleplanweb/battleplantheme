<?php 
/* Battle Plan Web Design Functions: Contact Form 7
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Set Up Contact Form 7

/*--------------------------------------------------------------
# Set Up Contact Form 7
--------------------------------------------------------------*/

// Set up spam filter for Contact Form 7 emails
add_filter( 'wpcf7_validate_textarea', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_text', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_text*', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_email', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_email*', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_tel', 'battleplan_contact_form_spam_blocker', 20, 2 );
add_filter( 'wpcf7_validate_tel*', 'battleplan_contact_form_spam_blocker', 20, 2 );
function battleplan_contact_form_spam_blocker( $result, $tag ) {	
    if ( stripos($tag->name,"message") !== false ) :
		$check = !is_array( $_POST["user-message"] ) && isset( $_POST["user-message"] ) ? trim( $_POST["user-message"] ) : ''; 
		$name = !is_array( $_POST["user-name"] ) && isset( $_POST["user-name"] ) ? trim( $_POST["user-name"] ) : ''; 
		$web_words = array('.com','http://','https://','.net','.org','www.','.buzz', 'bit.ly');
		if ( strtolower($check) == strtolower($name) ) $result->invalidate( $tag, 'Message cannot be sent.' );
		foreach($web_words as $web_word) :
			if (stripos($check,$web_word) !== false) $result->invalidate( $tag, 'In order to reduce spam, website addresses are not allowed.' );
		endforeach;		
	endif;
	if ( stripos($tag->name,"phone") !== false ) :
        $check = !is_array($_POST["user-phone"]) && isset( $_POST["user-phone"] ) ? trim( $_POST["user-phone"] ) : ''; 
		$bad_numbers = array('1234567');
		foreach($bad_numbers as $bad_number) :
			if (stripos($check,$bad_number) !== false) $result->invalidate( $tag, 'We do not accept messages without a valid phone number.');
		endforeach;
	endif;
	if ( stripos($tag->name,"email-confirm") !== false ) :
        $user_email = !is_array($_POST["user-email"]) && isset( $_POST['user-email'] ) ? trim( $_POST['user-email'] ) : '';
        $user_email_confirm = !is_array($_POST["user-email-confirm"]) && isset( $_POST['user-email-confirm'] ) ? trim( $_POST['user-email-confirm'] ) : '';
        if ( $user_email != $user_email_confirm ) $result->invalidate( $tag, "Are you sure this is the correct email?" );
    endif;
    return $result;
}

// Contact Form 7 email - format phone numbers
add_filter( 'wpcf7_posted_data', 'battleplan_formatMail', 10, 1 ); 
function battleplan_formatMail( $posted_data ) { 
	foreach ($posted_data as $key => $value) :
		$types = array ('phone', 'cell', 'mobile', 'fax');
		foreach ($types as $type) :
			if ( strpos( $key, $type ) !== FALSE) :
				$phone = preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $posted_data[$key]). "\n";
				$posted_data[$key] = $phone;
			endif;
		endforeach;
	endforeach;		
    return $posted_data;	
}; 

// Contact Form 7 email - prepare email for sending
add_action( 'wpcf7_before_send_mail', 'battleplan_setupFormEmail', 10, 3 ); 
function battleplan_setupFormEmail( $contact_form, &$abort, $submission ) { 
	$formMail = $contact_form->prop( 'mail' );
	$userLoc = $_COOKIE['user-city'].', '.$_COOKIE['user-region'];
	$userCountry = $_COOKIE['user-country'];
	$userViews = $_COOKIE['pages-viewed'];
	if ( $userViews == 1 ) : $userViews = "1 page"; else: $userViews = $userViews." pages"; endif;
	$userAgent = $_SERVER['HTTP_USER_AGENT'];
	$userDevice = is_mobile() ? "a mobile device" : "a desktop";
	if ( strpos($userAgent, "Mac") ) $userDevice = "a Mac";
	if ( strpos($userAgent, "iPod") ) $userDevice = "an iPod";
	if ( strpos($userAgent, "iPad") ) $userDevice = "an iPad";
	if ( strpos($userAgent, "iPhone") ) $userDevice = "an iPhone";
	if ( strpos($userAgent, "Android") ) $userDevice = "an Android";
	if ( strpos($userAgent, "iOS") ) $userSystem = " running iOS";
	if ( strpos($userAgent, "Windows") ) $userSystem = " running Windows";	
	if ( strpos($userAgent, "Linux") ) $userSystem = " running Linux";
	
	// filter recipient		
	if ( strpos($formMail['recipient'], "get-biz") !== false ) $formMail['recipient'] = do_shortcode($formMail['recipient']);
	
	// filter body
	$bodyEls = explode("\n", $formMail['body']);	
	$buildEmail = '<div style="line-height:1.5"><p><b style="font-size:130%">'.substr($formMail['subject'], 0, strpos($formMail['subject'], " · ")).'</b></p><p>';	
	
	$maxLength = 0;		
	foreach ( $bodyEls as $bodyEl ) :
		$elParts = explode("[", $bodyEl);
		if ( $elParts[0] && $elParts[1] ) : if ( strlen($elParts[0]) > $maxLength ) : $maxLength = strlen($elParts[0]); endif; endif;
	endforeach;	
	$colWidth = round($maxLength * 12);
	
	foreach ( $bodyEls as $bodyEl ) :
		$elParts = explode("[", $bodyEl);
		if ( $elParts[0] && $elParts[1] ) $buildEmail .= '<span style="display:inline-block; width:'.$colWidth.'px; style="font-size:87%"><em><b>'.$elParts[0].'</b></em></span>';
		if ( $elParts[0] && !$elParts[1] ) $buildEmail .= '<span style="display:inline-block; width:100%; style="font-size:87%"><em><b>'.$elParts[0].'</b></em></span>';
		if ( $elParts[0] && $elParts[1] ) $buildEmail .= '<span>['.$elParts[1].'</span>';		
		if ( !$elParts[0] && $elParts[1] ) $buildEmail .= '<span>['.$elParts[1].'</span>';
		$buildEmail .= '<br>';	
	endforeach;
	
	$buildEmail .= '</p></div><div style="line-height:1.5; border-top: 1px solid #8a8a8a; color: #8a8a8a; margin-top:5em;"><p>Sent from the <em>'.get_the_title(url_to_postid($_SERVER['HTTP_REFERER'])).'</em> page on the '.$GLOBALS['customer_info']['name'].' website.</p>';	
	
	$buildEmail .= '<p>Sender viewed';
	if ( $_COOKIE['pages-viewed'] ) $buildEmail .= ' '.$userViews;
	$buildEmail .= ' using '.$userDevice.$userSystem;
	if ( $userLoc ) $buildEmail .= ' near '.$userLoc;
	$buildEmail .= '.<br>';
	$buildEmail .= '<em>Sender IP:</em> <a style="text-decoration:none; color:#8a8a8a;" href="https://whatismyipaddress.com/ip/'.$_SERVER["REMOTE_ADDR"].'">'.$_SERVER["REMOTE_ADDR"].'</a><br>';
	
	$formMail['body'] = $buildEmail;		
		
	// intercept spammers	
	$ip = $_SERVER["REMOTE_ADDR"];
	$name = $submission->get_posted_data( 'user-name' );
	$email = $submission->get_posted_data( 'user-email' );
	$phone = $submission->get_posted_data( 'user-phone' );
	//$city = $submission->get_posted_data( 'user-city' );
	$message = $submission->get_posted_data( 'user-message' );
	
	$bad_ips = array('23.83.87', '37.72.186', '38.152.134', '45.87.214', '45.89.173', '45.134.140', '51.178.81', '91.219.212', '91.245.254', '93.190.14', '95.26.142', '96.47.236', '103.115.185', '104.152.222', '104.206.162', '104.234.212', '104.244.209', '104.254.92', '107.150.64', '107.158.118', '108.181.176', '108.236.79', '128.90.145', '137.255.24', '138.199.52', '143.244.44', '144.126.131', '161.123.150', '172.245.195', '181.215.16', '185.230.126', '192.3.93', '196.51.53', '198.44.133', '198.202.171', '199.187.211', '199.249.230', '207.244.71', '212.102.57');	

	$bad_emails= array($_SERVER['HTTP_HOST'], 'testing.com', 'test@', 'b2blistbuilding', 'amy.wilsonmkt@gmail', 'agency.leads.fish', 'landrygeorge8@gmail', 'digitalconciergeservice', 'themerchantlendr', 'fluidbusinessresources', 'focal-pointcoaching.net', 'zionps', 'rddesignsllc', 'domainworld', 'marketing.ynsw@gmail', 'seoagetechnology@gmail', 'excitepreneur.net', 'bullmarket.biz', 'tworld', 'garywhi777@gmail', 'ronyisthebest16@gmail', 'ronythomas611@gmail', 'ronythomasrecruiter@gmail', 'ideonagency.net', 'axiarobbie20@gmail', 'hyper-tidy', 'readyjob.org', 'thefranchisecreatornetwork', 'franchisecreatormarketing', 'legendarygfx', 'hitachi-metal-jp', 'expresscommerce.co', 'zaphyrpro', 'erjconsult', 'christymkts@gmail', 'theheritageseo', 'freedomwebdesigns', 'wesavesmallbusinesses@gmail', 'bimservicesllc.net', 'spamhunter.co', 'myspamburner.co', 'spamshield.co', 'excelestimation.net', 'dmccreativesolutions', 'mdhmx', 'digitalmarketingvas', 'rushmoreblueprint.co', 'answeraide', 'servicesuite.io', 'webtechxpress', 'medicopostura', 'anna.cramer@outlook', 'stephania.sander@yahoo', 'yourmarketingagencyfuture@gmail', '.pawsafer.sale', 'wexinc', 'erjsolutions', 'frequentlyonline', 'thawkingo', 'podiatristusa', 'besocialworldwide', 'taylah.jordan@gmail', 'garzagaragedoors', 'westholtmed.net', 'agape1life', 'bayougraphics', 'betterfinancialsolution', 'betterbusinessedge', 'econnectlocal', 'sbiestimationll', 'zentrades.pro', 'appfactoryhub', 'caredogbest', 'w-bmason', 'vibrantestimation', 'tylersupplycompany', 'steinerseo', 'foxmail', 'posicionamientoparapymes', 'testeurpascher', 'sbi-estimation', 'est.sbiestimation', 'jebcapitalpartners', 'bestcontractorsites', 'secondestimationllc', 'ipayperlead', 'difusionagencia', 'seedranchflavor', 'grupoiasa', 'hedgestone', '6pmarketing', 'sowsustainability', 'xruma', 'businesscoachvas', 'costestimating', 'theubique-group', 'earnmillions', 'logodesignsteam', 'gracegroupsllc', 'rushmoreblueprintpartners', 'wiseins1', 'cleaning-dallas', 'financingmycustomers', 'innovenservices.com', 'ismael57morenozvm@outlook.com', 'vasdirect.com', 'webmai.co', 'hdsupply.com', 'automisly', 'flinnrgs32', '.co.uk', 'OYOapp.com', 'getoffyourhighhorse', 'advancedbodyscan.com', 'clientcaf.info', 'brucesilverman.outsourcing', 'clientcaf.info', 'chemtreat.com');
	
	$bad_words = array('и','д','б','й','л','ы','З','у','Я','à','ô','ố','ế','á','ủ','ạ', 'fuck', 'Dear Customer', 'Dear Sales', 'Sir/Madam', 'Sir/Madame', 'Hello Business Owner', 'HELLO SALES', 'bitcoin', 'mаlwаre', 'antivirus', 'marketing', 'SEO', 'Wordpress', 'Cost Estimation', 'Guarantee Estimation', 'World Wide Estimating', 'Get more reviews, Get more customers', 'We write the reviews', 'write an article', 'a free article', 'keyword targeted traffic', 'rank your google', 'write you an article', 'write a short article', 'website home page design', 'updated version of your website', 'free sample Home Page', 'completely Free', 'Dear Receptionist', 'Franchise Creator', 'rebrand your business', 'what I would suggest for your website', 'organic traffic', 'We do Estimation', 'get your site published', 'high quality appointments and leads', 'new website', 'Does this sound interesting?', 'I notice that your website is very basic', 'appeal to more clients', 'improve your sales', 'free estimate from our company', 'blocks spam leads', 'block spam messages', 'In order to get a better idea of our work', 'Would you be interested in an article', 'cost estimates and take-off', 'If you\'ve made it this far', 'home services advertising', 'Do you need help with graphic design', 'I have an Audit of your website', 'Can we talk about your Website?', 'cooperate with your company', 'influencers on Instagram', 'procuring below items', 'Optimizing your website', 'your website could be', 'blog posts they write', 'mobile app development', 'Your website could benefit', 'available for download', 'at no cost', 'boost your business', 'targeted Customers', 'We help you get', 'designing and development', 'create your Website', 'fix a few things on your website', 'warnings found on your website', 'contact form blasting', 'make money online', 'not an AI haha', 'send over the set of plans', 'audit on your website', 'audit on your site', 'Are you in need of', 'kingcontacts', 'suggestions for your site', 'Using Google Adsense', 'a few issues with your website', 'very profitable business matter', 'needs of business owners', 'offer some suggestions for your website', 'analyzed your website', 'He querido escribirte porque veo una excelente', 'enhance your online reputation', 'According to the documents', 'Can you ship to Barbados', 'Freelance Web Designer', 'Our estimating services can help you', 'We have FREE opportunity', 'MyEListing', 'food packaging company', 'sexy pictures', 'Publicamos en periódicos', '1st page of Google', 'Need Accurate Estimate', 'Take-off Packages', 'data harvesting services', 'Accurate Quantity Take-offs', 'collaborate with your company', 'partner with you', 'business brokers that represent buyers', 'an official quotation', 'a company based in the Philippines', 'prefer not to hear from me again', 'short term investment', 'review provider', 'Myspace group', 'penning this article', 'abide by your requests', 'premium databases', 'Getting Reviews', 'estimating services', 'Supercharge your GMB listing', 'top pages of google', 'based in India', 'UncoverHiddenProfits', 'helping your business make money', 'find higher quality leads', 'estimating/architectural', 'opportunity to provide you', 'build business credit', 'eliminate personal guarantees', 'untangle my tax situation', 'exclusive sales training event', 'your website is in a great design', 'Are Your Hiring A Full Time HVAC Tech', 'less than perfect credit customers', 'Can you take on more clients', 'excellent option for prospective entrepreneurs', 'I noticed a few things that could use some fixing', 'I apologize for my cold outreach', 'Our answering service frees you up', 'no-strings-attached call', 'Kegel Devices', 'N/A', 'web development company', 'Mary Kay Sales Director', 'Odena', 'Kouvach', 'audit of your website', 'Ozempic', 'Wegovy', 'enhance your website', 'no-obligation proposal', 'summary of the audit results', '<p>', 'XRumer 23 StrongAI' );	
	
	$bad_names = array();
	
	$bad_phones = array('0', '(0', '(11)');

	$spamIntercept = '';	
	
	if ( $userCountry !== "United States" ) :	
		$countryIgnore		= 	["Chicken Dinner House", "Sweetie Pie", "Cooks Country"];
		$countryBlock		=	["Greater Fort Myers Dog Club"];
		$blockThis = false;
		$blockReason = " Country;";

		foreach ( $countryBlock as $site ) {
			if ( stripos($buildEmail, $site) !== false ) {
				$blockThis = true;
				break;
			}
		}
	
		if ( $message == '' ) $blockThis = true;	
	
		if ( ($_COOKIE['user-city'] === '' || $_COOKIE['user-city'] === null) 
			&& ($_COOKIE['user-region'] === '' || $_COOKIE['user-region'] === null)
			&& $userDevice === "a desktop"
			&& $userSystem === " running Windows" ) :
				$blockThis = true;
				$blockReason = " Suspicious;";
		endif;
	
		foreach ( $countryIgnore as $site ) {
			if ( stripos($buildEmail, $site ) !== false ) {
				$blockThis = false;
				break;
			}
		}
	
		if ( $blockThis ) $spamIntercept .= $blockReason;
	endif;
	
	foreach($bad_ips as $bad_ip) :
		if (stripos($ip, $bad_ip) !== false) $spamIntercept .= ' IP;';
	endforeach;	

	foreach($bad_emails as $bad_email) :
		if (stripos($email, $bad_email) !== false) $spamIntercept .= ' Email;';
	endforeach;
	
	foreach($bad_phones as $bad_phone) :
		if (strpos($phone, $bad_phone) === 0) $spamIntercept .= ' Phone;';
	endforeach;

	foreach($bad_words as $bad_word) :
		if (stripos($message, $bad_word) !== false) $spamIntercept .= ' Words;';
	endforeach;

	foreach($bad_names as $bad_name) :
		if (stripos($name, $bad_name) !== false) $spamIntercept .= ' Names;';
	endforeach;
	
	if ( $spamIntercept != '' ) : $formMail['recipient'] = 'email@battleplanwebdesign.com'; $formMail['subject'] = '<- SPAM: Blocked' .$spamIntercept .'-> '.$formMail['subject']; endif;
	
	// send email
	$contact_form->set_properties( array( 'mail' => $formMail ) );
	
	// update list of bad ips to block
	update_option( 'bp_bad_ips', $bad_ips );	 	
}; 	
         
// Block loading of refill file (Contact Form 7) to help speed up sites
//add_action('wp_footer', 'battleplan_no_contact_form_refill', 99); 
function battleplan_no_contact_form_refill() { 
	if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {	
		?><script nonce="<?php echo _BP_NONCE; ?>">wpcf7.cached = 0;</script><?php	
	}
}

// Add shortcode capability to Contact Form 7
add_filter( 'wpcf7_form_elements', 'do_shortcode' );

// Remove auto <br> from inside forms 
add_filter('wpcf7_autop_or_not', '__return_false');