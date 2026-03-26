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

	foreach ($bad_phones as $bad_phone) {
		if (strpos($phone, $bad_phone) === 0) {
			$spamIntercept = 'Phone';
			break;
		}
	}
	if ($spamIntercept) goto spam_done;

	// URL check on message — belt-and-suspenders in case the CF7 field isn't named "message"
	$url_spam_words = ['http://', 'https://', 'www.', 'bit.ly', 'ow.ly', 't.co/', 'goo.gl'];
	foreach ($url_spam_words as $w) {
		if (stripos($message, $w) !== false) {
			$spamIntercept = 'URL';
			break;
		}
	}
	if ($spamIntercept) goto spam_done;

	// Sales / marketing outreach phrase check
	$sales_phrases = [
		// Pricing patterns — nobody pitching a product is a real customer
		'/mo ', '/mo.', '/mo,', '/month flat', '/month (', 'per month', 'per-minute fee',
		// SaaS / free trial CTAs
		'free trial', 'free for 14', '14-day free', 'no credit card',
		// Lead gen / AI sales tool language
		'missed leads', 'missed calls', 'qualify leads', 'qualified leads',
		'book appointments', 'books appointments', 'ai receptionist',
		// SEO / traffic sales language
		'targeted visitors', 'outperforming paid ads', 'ai search first',
		'didn\'t show up', 'didn\'t appear', 'emergency hvac', 'searches homeowners use',
		// Cold email openers
		'i came across your business', 'i came across your website',
		'i was researching', 'ran the searches',
		// Exclusivity / territory sales tactics
		'we only take on one', 'one partner per', 'dominant local', 'own their entire territory',
		// CTAs that appear in cold sales emails, not customer inquiries
		'reply here and we', 'happy to show you what i found', 'no cost, just useful',
		'recover $', 'open to a brief chat', 'see if we align',
	];
	foreach ($sales_phrases as $phrase) {
		if (stripos($full_email, $phrase) !== false) {
			$spamIntercept = 'Sales';
			break;
		}
	}
	if ($spamIntercept) goto spam_done;

	// AI spam filter
	if (defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY) {
		$site_name    = $customer_info['name'] ?? get_bloginfo('name');
		$site_type    = $customer_info['type'] ?? 'local service business';
		$ai_prompt    = "You are a spam filter for contact forms on local service business websites.\n\n"
			. "Reply with ONLY one word: SPAM or OK.\n\n"
			. "Business: {$site_name} ({$site_type})\n"
			. "Sender country: {$userCountry}\n"
			. "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\nMessage: {$message}\n\n"
			. "Mark SPAM if the submission is any of these:\n"
			. "- A cold sales pitch or marketing solicitation (SEO, ads, AI tools, software, lead gen, call answering services)\n"
			. "- Business-to-business outreach where the sender is trying to SELL something to the business\n"
			. "- A generic cold-email opener (\"I came across your business\", \"I was researching [city] companies\", \"I noticed you're not showing up\")\n"
			. "- Contains pricing for something being sold (\$X/mo, free trial, no credit card needed)\n"
			. "- Reputation management, web design, SEO, or digital marketing solicitation\n"
			. "- From outside the US contacting a local US trade business with no clear service need\n\n"
			. "Mark OK if the submission is:\n"
			. "- A homeowner or customer asking about {$site_type} services\n"
			. "- An appointment, estimate, or quote request\n"
			. "- Someone describing a problem they need fixed\n"
			. "- An existing customer following up";

		$ai_response = wp_remote_post('https://api.anthropic.com/v1/messages', [
			'timeout' => 8,
			'headers' => [
				'x-api-key'         => ANTHROPIC_API_KEY,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			],
			'body' => json_encode([
				'model'      => 'claude-haiku-4-5-20251001',
				'max_tokens' => 5,
				'messages'   => [['role' => 'user', 'content' => $ai_prompt]],
			]),
		]);

		if (!is_wp_error($ai_response) && wp_remote_retrieve_response_code($ai_response) === 200) {
			$ai_body    = json_decode(wp_remote_retrieve_body($ai_response), true);
			$ai_verdict = strtoupper(trim($ai_body['content'][0]['text'] ?? ''));
			if (str_starts_with($ai_verdict, 'SPAM')) $spamIntercept = 'AI';
		}
	}

	spam_done:
	if ( $spamIntercept !== '' && stripos($buildEmail, "Babe's Chicken Catering" ) === false ) :

		$formMail['recipient'] = 'email@bp-webdev.com';
		$formMail['subject']   = '<- SPAM: Blocked ' . $spamIntercept . ' -> ' . $formMail['subject'];

		// --- add IP to central list + central log ---
		$central = 'https://bp-webdev.com/wp-content/email-add-ip.php';
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