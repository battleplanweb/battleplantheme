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
	
	$bad_ips = array('5.42.65', '5.62.58', '5.157', '5.254.43', '15.204.148', '18.130.68', '23.19.248', '23.81', '23.82.28', '23.83.87', '23.95', '23.104.162', '23.105.159', '23.108', '23.231.3', '23.237.26', '31.14.75', '37.19', '37.46.122', '37.72.186', '37.120.2', '37.140.254', '37.204.103', '38.125.112', '38.152.134', '38.153.99', '43.230.192', '45.35.195', '45.74.6', '45.87.214', '45.89.173', '45.90.218', '45.134.140', '45.136.14', '45.181.230', '45.248.55', '46.8.210', '46.101.195', '47.49.112', '47.230.75', '49.149.110', '50.3.196', '51.68.196', '51.77.195', '51.178.81', '51.178.91', '62.122.184', '63.141.62', '63.161.26', '66.235.168', '67.81.9', '67.201.33', '68.101.117', '68.183.245', '69.14.88', '71.238.251', '76.90.2', '76.122.91', '76.182.232', '77.81.142', '78.85.105', '78.154.64', '80.91.116', '81.94.78', '82.165.80', '82.221.113', '83.171.73', '84.145.116', '86.62.96', '88.99.166', '89.45', '91.197.3', '91.219.212', '91.223.133', '91.245.254', '92.18.161', '92.255.85', '93.120.188', '93.190.140', '93.193.189', '95.26.142', '95.65.104', '96.47.236', '98.175.31', '100.33.61', '103.9.79', '103.16.29', '103.115.185', '103.117.194', '103.159.116', '103.168.234', '103.169.21', '103.239.54', '104.148.28', '104.152.222', '104.168', '104.197.250', '104.206.162', '104.227.228', '104.234.212', '104.244.209', '104.248.158', '104.254.92', '107.150.64', '107.158.118', '107.174', '107.175', '108.181.176', '108.236.79', '113.190.122', '115.76.48', '116.111.239', '116.203.246', '119.41.203', '123.25.218', '128.90.145', '138.199.18', '138.199.38', '138.199.52', '139.171.13', '142.54.235', '143.244.44', '144.126.131', '146.70', '147.135.78', '149.34.24', '149.36.48', '149.102.22', '151.237.186', '154.3.232', '154.6.12.45', '154.9.177', '154.13.56', '154.13.63', '154.38.146', '154.47.22', '154.55.88', '156.146.5', '159.253.120', '161.34.39', '161.123.150', '162.0.205', '162.212.17', '162.218.15', '164.68.96', '165.231', '167.99.134', '171.238.153', '172.58.241', '172.93.128', '172.94.53', '172.245.195', '173.44', '173.213.85', '173.249.31', '176.124.210', '176.210.139', '178.62.40', '181.177', '181.191.233', '181.214.107', '181.215.16', '182.253.208', '185.104.113', '185.147.214', '185.164.74', '185.198.240', '185.203.7', '185.220.101', '185.230.126', '185.254.64', '186.179', '187.35.144', '190.142.197', '191.101.118', '192.3.93', '192.46.20', '192.53.6', '192.111.128', '192.186', '193.35.48', '193.37.254', '193.178.169', '194.156.97', '195.181.1', '195.252.203', '196.51.53', '196.196.23', '196.247.46', '197.210.8', '197.211.43', '198.12.255', '198.44.133', '198.145.103', '198.199.64', '198.202.171', '199.85.208', '199.187.211', '199.249.230', '202.4.121', '202.166.205', '204.186.70', '206.228.11', '207.244.71', '212.102.57', '213.14.233', '213.136.79', '216.249.100', '223.215.177');	

	$bad_emails= array($_SERVER['HTTP_HOST'], 'testing.com', 'test@', 'b2blistbuilding', 'amy.wilsonmkt@gmail', 'agency.leads.fish', 'landrygeorge8@gmail', 'digitalconciergeservice', 'themerchantlendr', 'fluidbusinessresources', 'focal-pointcoaching.net', 'zionps', 'rddesignsllc', 'domainworld', 'marketing.ynsw@gmail', 'seoagetechnology@gmail', 'excitepreneur.net', 'bullmarket.biz', 'tworld', 'garywhi777@gmail', 'ronyisthebest16@gmail', 'ronythomas611@gmail', 'ronythomasrecruiter@gmail', 'ideonagency.net', 'axiarobbie20@gmail', 'hyper-tidy', 'readyjob.org', 'thefranchisecreatornetwork', 'franchisecreatormarketing', 'legendarygfx', 'hitachi-metal-jp', 'expresscommerce.co', 'zaphyrpro', 'erjconsult', 'christymkts@gmail', 'theheritageseo', 'freedomwebdesigns', 'wesavesmallbusinesses@gmail', 'bimservicesllc.net', 'spamhunter.co', 'myspamburner.co', 'spamshield.co', 'excelestimation.net', 'dmccreativesolutions', 'mdhmx', 'digitalmarketingvas', 'rushmoreblueprint.co', 'answeraide', 'servicesuite.io', 'webtechxpress', 'medicopostura', 'anna.cramer@outlook', 'stephania.sander@yahoo', 'yourmarketingagencyfuture@gmail', '.pawsafer.sale', 'wexinc', 'erjsolutions', 'frequentlyonline', 'thawkingo', 'podiatristusa', 'besocialworldwide', 'taylah.jordan@gmail', 'garzagaragedoors', 'westholtmed.net', 'agape1life', 'bayougraphics', 'betterfinancialsolution', 'betterbusinessedge', 'econnectlocal', 'sbiestimationll', 'zentrades.pro', 'appfactoryhub', 'caredogbest', 'w-bmason', 'vibrantestimation', 'tylersupplycompany', 'steinerseo', 'foxmail', 'posicionamientoparapymes', 'testeurpascher', 'sbi-estimation', 'est.sbiestimation', 'jebcapitalpartners', 'bestcontractorsites', 'secondestimationllc', 'ipayperlead', 'difusionagencia', 'seedranchflavor', 'grupoiasa', 'hedgestone', '6pmarketing', 'sowsustainability', 'xruma', 'businesscoachvas', 'costestimating', 'theubique-group', 'earnmillions', 'logodesignsteam', 'gracegroupsllc', 'rushmoreblueprintpartners', 'wiseins1', 'cleaning-dallas', 'financingmycustomers', 'innovenservices.com', 'ismael57morenozvm@outlook.com', 'vasdirect.com', 'webmai.co', 'hdsupply.com', 'automisly', 'flinnrgs32', '.co.uk', 'getoffyourhighhorse');
	
	$bad_words = array('Dear Sales', 'Sir/Madam', 'bitcoin','mаlwаre','antivirus','marketing','SEO','Wordpress','Chiirp','@Getreviews','Cost Estimation','Guarantee Estimation','World Wide Estimating','Postmates delivery','health coverage plans','loans for small businesses','fuck','Get more reviews, Get more customers','We write the reviews','write an article','a free article','relocation checklist','keyword targeted traffic','downsizing your living space','Roleplay helps develop','rank your google','write you an article','write a short article','We want to write','website home page design','updated version of your website','free sample Home Page','completely Free','Dear Receptionist','Franchise Creator','John Romney','get in touch with ownership','rebrand your business', 'what I would suggest for your website','organic traffic','We do Estimation','get your site published','high quality appointments and leads', 'new website','Does this sound interesting?','I notice that your website is very basic','appeal to more clients','improve your sales','Exceptional Cleaners','free estimate from our company','blocks spam leads','block spam messages,','In order to get a better idea of our work','facility janitorial needs','Would you be interested in an article','SpamBurner','cost estimates and take-off','If you\'ve made it this far','home services advertising','Do you need help with graphic design','I have an Audit of your website', 'Can we talk about your Website?', 'HELLO SALES','we would like to cooperate with your company', 'big influencers on Instagram', 'complimentary cleaning analysis', 'procuring below items', 'Optimizing your website', 'your website could be more innovative', 'inspiration for many blog posts they write', 'acquiring a database', 'automate your cutsomer reviews', 'hybrid mobile app development', 'Your website could benefit greatly', 'available for download at no cost', 'Want to boost your business on Google?', 'We are Gplocean', 'Do you need targeted Customers', 'We help you get more business', 'designing and development', 'create your Website-Audit and a quote', 'fix a few things on your website', 'warnings found on your website', 'contact form blasting', 'Looking for an effortless way to make money online?', 'not an AI haha', 'send over the set of plans', 'audit on your website', 'earths bots known as humans', 'You just read this message right?', 'Checkout the Amazon biggest deals', 'InboxBlasts', 'audit on your site', 'handle all your auto repair needs', 'Are you in need of a reliable and skilled', 'Affordable Gutters Company', 'kingcontacts', 'some suggestions for your site', 'Using Google Adsense', 'a few issues with your website', 'very profitable business matter', 'needs of business owners', 'offer some suggestions for your website', 'analyzed your website', 'He querido escribirte porque veo una excelente', 'enhance your online reputation', 'According to the documents', 'Can you ship to Barbados', 'Freelance Web Designer', 'Our estimating services can help you', 'We have FREE opportunity', 'Sir/Madame', 'Hello Business Owner', 'MyEListing', 'food packaging company', 'sexy pictures', 'Publicamos en periódicos', '1st page of Google', 'Need Accurate Estimate/Take-off Packages', 'data harvesting services', 'Accurate Quantity Take-offs', 'I would like to collaborate with your company', 'partner with you', 'business brokers that represent buyers', 'an official quotation', 'a company based in the Philippines', 'prefer not to hear from me again', 'short term investment', 'review provider', 'Myspace group', 'penning this article', 'advice and guidance', 'abide by your requests', 'premium databases', 'Getting Reviews', 'estimating services', 'Supercharge your GMB listing', 'clientcaf.info', 'chemtreat.com', 'top pages of google', 'based in India', 'UncoverHiddenProfits', 'helping your business make money', 'find higher quality leads', 'estimating/architectural', 'opportunity to provide you', 'Thanks, Chloe', 'build business credit', 'eliminate personal guarantees', 'untangle my tax situation', 'exclusive sales training event', 'your website is in a great design', 'Are Your Hiring A Full Time HVAC Tech', 'less than perfect credit customers', 'Can you take on more clients', 'excellent option for prospective entrepreneurs', 'I noticed a few things that could use some fixing', 'I apologize for my cold outreach', 'Our answering service frees you up', 'no-strings-attached call', 'Kegel Devices', 'N/A', 'web development company', 'Mary Kay Sales Director', 'Odena', 'Kouvach', 'audit of your website', 'Ozempic', 'Wegovy', 'enhance your website', 'и','д','б','й','л','ы','З','у','Я','à','ô','ố','ế','á','ủ','ạ' );	
	
	$bad_names = array('Steve Daniels', 'Mark Rogers', 'Kevin Miller');
	
	$bad_phones = array('0', '(0', '(11)');

	$spamIntercept = '';	
	
	if ( $userCountry != "United States" ) :	
		$countryIgnore		= 	["Chicken Dinner House", "Sweetie Pie", "Cooks Country"];
		$countryBlock		=	["Greater Fort Myers Dog Club", "Air Zone Experts"];
		$blockThis = false;

		foreach ( $countryBlock as $site ) {
			if ( stripos($buildEmail, $site) !== false ) {
				$blockThis = true;
				break;
			}
		}
	
		if ( $message == '' ) $blockThis = true;	
	
		foreach ( $countryIgnore as $site ) {
			if ( stripos($buildEmail, $site ) !== false ) {
				$blockThis = false;
				break;
			}
		}
	
		if ( $blockThis ) $spamIntercept .= ' Country;';
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