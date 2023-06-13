<?php 
/* Battle Plan Web Design Functions: Main
 
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
	$userLoc = $_COOKIE['user-loc'];
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
	//$name = $submission->get_posted_data( 'user-name' );
	$email = $submission->get_posted_data( 'user-email' );
	$phone = $submission->get_posted_data( 'user-phone' );
	//$city = $submission->get_posted_data( 'user-city' );
	$message = $submission->get_posted_data( 'user-message' );
	
	$bad_ips = array('5.254.43', '15.204.148', '23.81.62', '23.237.26', '37.19.199', '37.19.205', '37.19.221', '37.46.122', '38.125.112', '38.153.99', '45.89.173', '45.248.55', '63.141.62', '63.161.26', '82.221.113', '89.187.177', '89.187.179', '89.187.180', '91.197.36', '91.197.37', '91.223.133', '93.190.140', '104.148.28', '104.227.228', '138.199.38', '138.199.52', '143.244.44', '146.70.45.85', '146.70.147', '154.3.232', '154.9.177', '154.13.56', '154.13.63', '154.38.146', '156.146.54', '161.123.150', '162.218.15', '172.94.53', '173.213.85', '181.214.107', '185.147.214', '185.254.64', '192.46.200.146', '192.46.203', '192.53.67', '192.53.69', '195.181.163', '195.181.171', '196.51.53', '198.145.103', '199.85.208', '206.228.117', '206.228.118');

	$bad_emails= array($_SERVER['HTTP_HOST'], 'testing.com', 'test@', 'b2blistbuilding.com', 'amy.wilsonmkt@gmail.com', '@agency.leads.fish', 'landrygeorge8@gmail.com', '@digitalconciergeservice.com', '@themerchantlendr.com', '@fluidbusinessresources.com', '@focal-pointcoaching.net', '@zionps.com', '@rddesignsllc.com', '@domainworld.com', 'marketing.ynsw@gmail.com', 'seoagetechnology@gmail.com', '@excitepreneur.net', '@bullmarket.biz', '@tworld.com', 'garywhi777@gmail.com', 'ronyisthebest16@gmail.com', 'ronythomas611@gmail.com', 'ronythomasrecruiter@gmail.com', '@ideonagency.net', 'axiarobbie20@gmail.com', '@hyper-tidy.com', '@readyjob.org', '@thefranchisecreatornetwork.com', 'franchisecreatormarketing.com', '@legendarygfx.com', '@hitachi-metal-jp.com', '@expresscommerce.co', '@zaphyrpro.com', 'erjconsult.com', 'christymkts@gmail.com', '@theheritageseo.com', '@freedomwebdesigns.com', 'wesavesmallbusinesses@gmail.com', '@bimservicesllc.net', '@spamhunter.co', '@myspamburner.co', '@spamshield.co', '@excelestimation.net', '@dmccreativesolutions.com', '@mdhmx.com', '@digitalmarketingvas.com', '@rushmoreblueprint.co', '@answeraide.com', '@servicesuite.io', '@webtechxpress.com');
	
	$bad_words = array('Pandemic Recovery','bitcoin','mаlwаre','antivirus','marketing','SEO','Wordpress','Chiirp','@Getreviews','Cost Estimation','Guarantee Estimation','World Wide Estimating','Postmates delivery','health coverage plans','loans for small businesses','New Hire HVAC Employee','SO BE IT','profusa hydrogel','Divine Gatekeeper','witchcraft powers','I will like to make a inquiry','Mark Of The Beast','fuck','dogloverclub.store','Getting a Leg Up','ultimate smashing machine','Get more reviews, Get more customers','We write the reviews','write an article','a free article','relocation checklist','Rony (Steve', 'Your company Owner','We are looking forward to hiring an HVAC contracting company','keyword targeted traffic','downsizing your living space','Roleplay helps develop','rank your google','TRY IT RIGHT NOW FOR FREE','house‌ ‌inspection‌ ‌process', 'write you an article','write a short article','We want to write','website home page design','updated version of your website','free sample Home Page','completely Free','Dear Receptionist','Franchise Creator','John Romney','get in touch with ownership','rebrand your business', 'what I would suggest for your website', 'Virtual Assistant Services','Would your readers','organic traffic','We do Estimation','get your site published','high quality appointments and leads', 'new website','Does this sound interesting?','I notice that your website is very basic','appeal to more clients','improve your sales','Exceptional Cleaners','free estimate from our company','blocks spam leads','block spam messages,','In order to get a better idea of our work','facility janitorial needs','I\'m a telemarketer','block unwanted messages','Would you be interested in an article','SpamBurner','cost estimates and take-off','If you\'ve made it this far','home services advertising','Do you need help with graphic design','I have an Audit of your website', 'Can we talk about your Website?', 'HELLO SALES','we would like to cooperate with your company', 'big influencers on Instagram', 'complimentary cleaning analysis', 'procuring below items', 'Optimizing your website', 'your website could be more innovative', 'inspiration for many blog posts they write', 'acquiring a database', 'automate your cutsomer reviews', 'hybrid mobile app development', 'Your website could benefit greatly', 'available for download at no cost', 'Want to boost your business on Google?', 'We are Gplocean', 'Do you need targeted Customers', 'We help you get more business', 'designing and development', 'create your Website-Audit and a quote', 'fix a few things on your website', 'contact form blasting', 'и','д','б','й','л','ы','З','у','Я');
	
	$spamIntercept = '';

	foreach($bad_ips as $bad_ip) :
		if (stripos($ip, $bad_ip) !== false) $spamIntercept .= ' IP;';
	endforeach;	

	foreach($bad_emails as $bad_email) :
		if (stripos($email, $bad_email) !== false) $spamIntercept .= ' Email;';
	endforeach;
	
	if (strpos($phone, '0') === 0 || strpos($phone, '(0') === 0) $spamIntercept .= ' Phone;';

	foreach($bad_words as $bad_word) :
		if (stripos($message, $bad_word) !== false) $spamIntercept .= ' Words;';
	endforeach;
	
	if ( $spamIntercept != '' ) : $formMail['recipient'] = 'email@battleplanwebdesign.com'; $formMail['subject'] = '<- SPAM: Blocked' .$spamIntercept .'-> '.$formMail['subject']; endif;
	
	$formMail['from_name'] = str_replace('=?utf-8?q?', '', $formMail['from_name']);
	$formMail['from_name'] = str_replace('_=C2=B7_', ' · ', $formMail['from_name']);
	
	// send email
	$contact_form->set_properties( array( 'mail' => $formMail ) );
}; 	
         
// Block loading of refill file (Contact Form 7) to help speed up sites
add_action('wp_footer', 'battleplan_no_contact_form_refill', 99); 
function battleplan_no_contact_form_refill() { 
	if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {	
		?><script nonce="<?php echo _BP_NONCE; ?>">wpcf7.cached = 0;</script><?php	
	}
}

// Add shortcode capability to Contact Form 7
add_filter( 'wpcf7_form_elements', 'do_shortcode' );

// Remove auto <br> from inside forms 
add_filter('wpcf7_autop_or_not', '__return_false');
?>