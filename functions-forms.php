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
add_filter('wpcf7_form_elements', function ($form) {
	$t = time();
	$h = substr(hash_hmac('sha256', (string)$t, $_SERVER['HTTP_HOST'] ?? 'bp'), 0, 12);
	$hp = '<span class="bp-hp" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">
		<label>Leave this empty<input type="text" name="bp_hp" value=""></label>
	</span>
	<input type="hidden" name="bp_t" value="'.$t.'">
	<input type="hidden" name="bp_h" value="'.$h.'">';
	return $form.$hp;
});

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
	$customer_info = customer_info();
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

	$buildEmail .= '</p></div><div style="line-height:1.5; border-top: 1px solid #8a8a8a; color: #8a8a8a; margin-top:5em;"><p>Sent from the <em>'.get_the_title(url_to_postid($_SERVER['HTTP_REFERER'])).'</em> page on the '.$customer_info['name'].' website.</p>';

	$buildEmail .= '<p>Sender viewed';
	if ( $_COOKIE['pages-viewed'] ) $buildEmail .= ' '.$userViews;
	$buildEmail .= ' using '.$userDevice.$userSystem;
	if ( $userLoc ) $buildEmail .= ' near '.$userLoc;
	$buildEmail .= '.<br>';
	$buildEmail .= '<em>Sender IP:</em> <a style="text-decoration:none; color:#8a8a8a;" href="https://whatismyipaddress.com/ip/'.$_SERVER["REMOTE_ADDR"].'">'.$_SERVER["REMOTE_ADDR"].'</a><br>';

	$formMail['body'] = $buildEmail;

	// intercept spammers
	$ip = $_SERVER["REMOTE_ADDR"];
	$spamIntercept = '';

	// Honeypot checks
	$hp = isset($_POST['bp_hp']) ? trim((string)$_POST['bp_hp']) : '';
	$ts = isset($_POST['bp_t']) ? (int)$_POST['bp_t'] : 0;
	$hv = isset($_POST['bp_h']) ? (string)$_POST['bp_h'] : '';
	$now = time();
	$hash_ok = hash_equals(substr(hash_hmac('sha256', (string)$ts, $_SERVER['HTTP_HOST'] ?? 'bp'), 0, 12), $hv);
	$too_fast = ($ts > 0) ? (($now - $ts) < 5) : true; // <5s fill = bot

	($hp !== '' || !$hash_ok || $too_fast) ? $spamIntercept = 'Bot' : null;

	function getField($submission, $keyword) {
		$data = $submission->get_posted_data();
		foreach ($data as $key => $value) {
			if (stripos($key, $keyword) !== false && !empty($value)) {
				return $value;
			}
		}
		return '';
	}

	$name = getField($submission, 'name');
	$email = getField($submission, 'email');
	$phone = getField($submission, 'phone');
	$message = getField($submission, 'message');

	$allData = array_filter($submission->get_posted_data(), function($v) {
		return !empty($v);
	});
	$full_email = implode(' ', $allData);

	if ($spamIntercept) goto spam_done;

	$search_words = ['fuck', 'shit', 'cunt', 'bitch'];
	$replace_words = ['####', '####', '####', '#####'];

	$message = str_replace($search_words, $replace_words, $message);

	$bad_emails= array($_SERVER['HTTP_HOST'], 'testing.com', 'test@', 'b2blistbuilding', 'amy.wilsonmkt@gmail', 'agency.leads.fish', 'landrygeorge8@gmail', 'digitalconciergeservice', 'themerchantlendr', 'fluidbusinessresources', 'focal-pointcoaching.net', 'zionps', 'rddesignsllc', 'domainworld', 'marketing.ynsw@gmail', 'seoagetechnology@gmail', 'excitepreneur.net', 'bullmarket.biz', 'tworld', 'garywhi777@gmail', 'ronyisthebest16@gmail', 'ronythomas611@gmail', 'ronythomasrecruiter@gmail', 'ideonagency.net', 'axiarobbie20@gmail', 'hyper-tidy', 'readyjob.org', 'thefranchisecreatornetwork', 'franchisecreatormarketing', 'legendarygfx', 'hitachi-metal-jp', 'expresscommerce.co', 'zaphyrpro', 'erjconsult', 'christymkts@gmail', 'theheritageseo', 'freedomwebdesigns', 'wesavesmallbusinesses@gmail', 'bimservicesllc.net', 'spamhunter.co', 'myspamburner.co', 'spamshield.co', 'excelestimation.net', 'dmccreativesolutions', 'mdhmx', 'digitalmarketingvas', 'rushmoreblueprint.co', 'answeraide', 'servicesuite.io', 'webtechxpress', 'medicopostura', 'anna.cramer@outlook', 'stephania.sander@yahoo', 'yourmarketingagencyfuture@gmail', '.pawsafer.sale', 'wexinc', 'erjsolutions', 'frequentlyonline', 'thawkingo', 'podiatristusa', 'besocialworldwide', 'taylah.jordan@gmail', 'garzagaragedoors', 'westholtmed.net', 'agape1life', 'bayougraphics', 'betterfinancialsolution', 'betterbusinessedge', 'econnectlocal', 'sbiestimationll', 'zentrades.pro', 'appfactoryhub', 'caredogbest', 'w-bmason', 'vibrantestimation', 'tylersupplycompany', 'steinerseo', 'foxmail', 'posicionamientoparapymes', 'testeurpascher', 'sbi-estimation', 'est.sbiestimation', 'jebcapitalpartners', 'bestcontractorsites', 'secondestimationllc', 'ipayperlead', 'difusionagencia', 'seedranchflavor', 'grupoiasa', 'hedgestone', '6pmarketing', 'sowsustainability', 'xruma', 'businesscoachvas', 'costestimating', 'theubique-group', 'earnmillions', 'logodesignsteam', 'gracegroupsllc', 'rushmoreblueprintpartners', 'wiseins1', 'cleaning-dallas', 'financingmycustomers', 'innovenservices.com', 'ismael57morenozvm@outlook.com', 'vasdirect.com', 'webmai.co', 'hdsupply.com', 'automisly', 'flinnrgs32', '.co.uk', 'OYOapp.com', 'getoffyourhighhorse', 'advancedbodyscan.com', 'clientcaf.info', 'brucesilverman.outsourcing', 'clientcaf.info', 'chemtreat.com', 'astoundz.com', 'xinyisolar.online', 'BISHOPKNIGHTLLC.COM', 'rezult.org', 'casey.swiftt@aol.com', 'aecom-usa.com', 'academicproductions.com','houseflippers.biz', 'virtualhandsupport.com', 'pursuitind.com', 'magwitch.com', 'toptalentvas', 'mzfederal', 'moneysquad', 'dadknowsdiy.com', 'cahillestimating', 'bestaitools', 'dynamicvirtualmanager', 'expertcellent', 'dctechnolabs', 'ip-advocaat.com', 'bizbuydave', 'trustedvirtualteam', 'thevirtualsalesgroup', 'servicecallsaver', 'tile-stonecraetions', 'catehvac', 'bovafoodsco', 'usestateboilerinspector', 'kunal-kakkar', 'globalpartfinder', 'vladislavdev', 'frontierenergy', 'insuretuckertn', 'doanything');

	$bad_words = array('и', 'д', 'б', 'й', 'л', 'ы', 'З', 'у', 'Я', 'à', 'ô', 'ố', 'ế', 'á', 'ủ', 'ạ', '湖', '結', '衣', '市', '翼', '清', '水', 'http://', 'https://', 'www.', 'Dear Customer', 'Dear Sales', 'Sir/Madam', 'Sir/Madame', 'Hello Business Owner', 'HELLO SALES', 'bitcoin', 'mаlwаre', 'antivirus', 'marketing', 'SEO', 'Wordpress', 'Cost Estimation', 'Guarantee Estimation', 'World Wide Estimating', 'Get more reviews, Get more customers', 'We write the reviews', 'write an article', 'a free article', 'keyword targeted traffic', 'rank your google', 'boost your leads', 'write you an article', 'write a short article', 'website home page design', 'updated version of your website', 'free sample Home Page', 'lead generation', 'completely Free', 'Dear Receptionist', 'Franchise Creator', 'rebrand your business', 'what I would suggest for your website', 'improving your website', 'organic traffic', 'more business leads', 'We do Estimation', 'get your site published', 'high quality appointments and leads', 'new website', 'Google’s 1st Page', 'Does this sound interesting?', 'I notice that your website is very basic', 'appeal to more clients', 'improve your sales', 'free estimate from our company', 'blocks spam leads', 'block spam messages', 'In order to get a better idea of our work', 'Would you be interested in an article', 'cost estimates and take-off', 'If you\'ve made it this far', 'home services advertising', 'Do you need help with graphic design', 'I have an Audit of your website', 'Can we talk about your Website?', 'cooperate with your company', 'influencers on Instagram', 'procuring below items', 'Optimizing your website', 'your website could be', 'blog posts they write', 'mobile app development', 'Your website could benefit', 'available for download', 'at no cost', 'boost your business', 'targeted Customers', 'We help you get', 'designing and development', 'create your Website', 'fix a few things on your website', 'warnings found on your website', 'contact form blasting', 'make money online', 'not an AI haha', 'send over the set of plans', 'audit on your website', 'audit on your site', 'Are you in need of', 'kingcontacts', 'suggestions for your site', 'Using Google Adsense', 'a few issues with your website', 'very profitable business matter', 'needs of business owners', 'offer some suggestions for your website', 'analyzed your website', 'He querido escribirte porque veo una excelente', 'enhance your online reputation', 'According to the documents', 'Can you ship to Barbados', 'Freelance Web Designer', 'Our estimating services can help you', 'We have FREE opportunity', 'MyEListing', 'food packaging company', 'sexy pictures', 'Publicamos en periódicos', '1st page of Google', 'Need Accurate Estimate', 'Take-off Packages', 'data harvesting services', 'Accurate Quantity Take-offs', 'collaborate with your company', 'partner with you', 'business brokers that represent buyers', 'an official quotation', 'a company based in the Philippines', 'prefer not to hear from me again', 'short term investment', 'review provider', 'Myspace group', 'penning this article', 'abide by your requests', 'premium databases', 'Getting Reviews', 'estimating services', 'Supercharge your GMB listing', 'top pages of google', 'based in India', 'UncoverHiddenProfits', 'helping your business make money', 'find higher quality leads', 'estimating/architectural', 'opportunity to provide you', 'build business credit', 'eliminate personal guarantees', 'untangle my tax situation', 'exclusive sales training event', 'your website is in a great design', 'Are Your Hiring A Full Time HVAC Tech', 'less than perfect credit customers', 'Can you take on more clients', 'excellent option for prospective entrepreneurs', 'I noticed a few things that could use some fixing', 'I apologize for my cold outreach', 'Our answering service frees you up', 'no-strings-attached call', 'Kegel Devices', 'N/A', 'web development company', 'Mary Kay Sales Director', 'Odena', 'Kouvach', 'audit of your website', 'Ozempic', 'Wegovy', 'enhance your website', 'no-obligation proposal', 'summary of the audit results', 'XRumer 23 StrongAI', 'WhatsApp: +', 'Most Demanded AI Apps', 'possible acquire some Hose Pipes', 'Good Day, I am inquiring on', 'Good discount pricing will be appreciated', 'fix your forwarding system', 'Please advise on how soon order can be shipped out', 'fastest and most efficient way to destroy your wealth', 'do you have surcharges when making payment', 'Whatsapp', 'AUDIT ME, AMMAR', 'competitors are attracting clients online', 'handle more clients', 'I can miss a lot of emails from spam', 'Confidential – Please Forward to Owner', 'website placements', 'homepage that seems out of place', 'simple chatbot', 'guaranteed form submission', 'Good day and would like to know', 'gesture of goodwill', 'local customers from your website', 'few opportunities to increase engagement', 'specialize in ad creatives', 'Reply YES', 'your site is absolutely outdated', 'This is not a sales pitch', 'better website', 'increasing your organic leads', 'online visibility', 'saw a bug on your website', 'optimized website conversions', ' I can support your business', 'reduce their operating costs', 'help you hit the Top 3', 'qualified local leads', 'affecting your search rankings', 'unable to complete the checkout process' );

	$bad_names = array('loraine68', 'theron.bode46', 'brenden.lebsack');

	$bad_phones = array('0', '(0', '(11)');

	if ( $userCountry !== "United States" ) :
		$countryIgnore		= 	["Chicken Dinner House", "Babe's Chicken Catering", "Sweetie Pie", "Cooks Country", "Rovin Inc"];
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
				$blockReason = "Suspicious";
		endif;

		foreach ( $countryIgnore as $site ) {
			if ( stripos($buildEmail, $site ) !== false ) {
				$blockThis = false;
				break;
			}
		}

		if ( $blockThis ) $spamIntercept = $blockReason;
	endif;

	if ($spamIntercept) goto spam_done;

	foreach ($bad_emails as $bad_email) {
		if (stripos($email, $bad_email) !== false) {
			$spamIntercept = 'Email';
			break;
		}
	}
	if ($spamIntercept) goto spam_done;

	foreach ($bad_phones as $bad_phone) {
		if (strpos($phone, $bad_phone) === 0) {
			$spamIntercept = 'Phone';
			break;
		}
	}
	if ($spamIntercept) goto spam_done;

	foreach ($bad_words as $bad_word) {
		if (stripos($full_email, $bad_word) !== false) {
			$spamIntercept = 'Word';
			break;
		}
	}
	if ($spamIntercept) goto spam_done;

	foreach ($bad_names as $bad_name) {
		if (stripos($name, $bad_name) !== false) {
			$spamIntercept = 'Name';
			break;
		}
	}

	spam_done:
	if ( $spamIntercept !== '' && stripos($buildEmail, "Babe's Chicken Catering" ) === false ) :

		$formMail['recipient'] = 'email@battleplanwebdesign.com';
		$formMail['subject']   = '<- SPAM: Blocked ' . $spamIntercept . ' -> ' . $formMail['subject'];

		// --- add IP to central list + central log ---
		$central = 'https://battleplanwebdesign.com/wp-content/email-add-ip.php';
		$secret  = 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c';

		$site = $_SERVER['HTTP_HOST']    ?? '';
		$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$uri  = $_SERVER['REQUEST_URI']  ?? '';
		$ref  = $_SERVER['HTTP_REFERER'] ?? '';
		$ts   = (string) time();

		$payload = $ip . '|' . $site . '|' . $uri . '|' . $ua . '|' . $ref . '|' . $ts;
		$sig     = hash_hmac('sha256', $payload, $secret);

		// non-blocking fire-and-forget
		wp_remote_post($central, [
			'timeout'      => 2,
			'redirection'  => 0,
			'blocking'     => false,
			'body'         => [
				'ip'   => $ip,
				'site'=> $site,
				'uri'  => $uri,
				'ua'   => $ua,
				'ref'  => $ref,
				'ts'   => $ts,
				'sig'  => $sig
			]
		]);
	endif;

	// send email
	$contact_form->set_properties( array( 'mail' => $formMail ) );
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