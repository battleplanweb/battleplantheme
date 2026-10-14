<?php
/* Battle Plan Web Design - Privacy Policy */

$num = 0;
$biz = '<b>[get-biz info="name"]</b>';
$email = '[get-biz info="email"]';
$phone = '[get-biz info="phone-link"]';
$state = '[get-biz info="state-full"]';

$printPage = '
    <h1>Privacy Policy for '.$biz.'</h1>

    <p>Last Updated: October 9, 2026</p>

    <p>Welcome to '.$biz.'. Your privacy is important to us. This Privacy Policy explains how we collect, use, disclose, retain, and protect personal information when you visit our website, request services, or participate in our customer communication programs.</p>

    <p>The terms "we," "us," and "our" refer to '.$biz.'; "you" refers to visitors, customers, and other individuals interacting with us. Personal information includes information that identifies or is reasonably linked to an identifiable individual.</p>

    <p>This policy covers information collected through our website, contact forms, chat, SMS/MMS, email, telephone communications, customer surveys, feedback requests, and related business interactions. Our website and communication programs are intended primarily for users in the United States. Information submitted from outside the United States may be processed and stored in the United States.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') INFORMATION WE COLLECT</strong></p>

    <p>We collect information you provide voluntarily, information generated through your interactions with us, and certain information collected automatically when you use our website.</p>

    <ul>
        <li><b>Contact Information:</b> Name, email address, telephone number, company, mailing address, service address, and location when you contact us or request service.</li>
        <li><b>Service Information:</b> Service requests, estimates, project details, appointment preferences, dates, photos, attachments, and notes you provide.</li>
        <li><b>Communications:</b> Information submitted through contact forms, website chat, text messages, emails, calls, and customer-support interactions.</li>
        <li><b>Marketing and Messaging Preferences:</b> Email or SMS subscription preferences, consent records, opt-in and opt-out status, and related communications information.</li>
        <li><b>Surveys and Feedback:</b> Customer satisfaction ratings, Net Promoter Scores (NPS), survey responses, qualitative feedback, and review-related communications.</li>
        <li><b>Usage and Device Data:</b> IP address, approximate location, browser type, operating system, device characteristics, pages visited, links clicked, referral sources, session information, timestamps, and diagnostic information.</li>
        <li><b>Cookies and Tracking Data:</b> Information collected through cookies, analytics tools, advertising measurement, website tags, pixels, and similar technologies where implemented.</li>
    </ul>

    <p>You may browse our website without voluntarily submitting personal information. However, certain information may be necessary to respond to inquiries, provide services, schedule appointments, or administer communication programs.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') HOW WE USE YOUR INFORMATION</strong></p>

    <p>We use information collected through our website and business communications for purposes including:</p>

    <ul>
        <li>Responding to inquiries, providing estimates, scheduling appointments, and delivering requested services.</li>
        <li>Sending service confirmations, reminders, invoices, project updates, and customer-support communications.</li>
        <li>Conducting post-project satisfaction surveys, collecting NPS scores, requesting feedback, and following up on customer concerns.</li>
        <li>Inviting customers to leave voluntary reviews on public review platforms.</li>
        <li>Sending informational or promotional communications where permitted by law and with any required consent.</li>
        <li>Operating, maintaining, troubleshooting, and improving our website and communication systems.</li>
        <li>Analyzing website traffic, measuring advertising effectiveness, and attributing inquiries or calls to marketing sources.</li>
        <li>Detecting fraud, preventing misuse, maintaining security, and complying with applicable legal obligations.</li>
    </ul>

    <p>We do not sell personal information for money or provide customer contact information to third parties for their independent marketing campaigns. Certain advertising technologies may involve data disclosures that are treated as sharing or selling under applicable privacy laws, as explained below.</p>

    <p>We do not use information collected through our website or communication programs to make solely automated decisions that produce legal or similarly significant effects concerning individuals.</p>

    <p>Where applicable, you may unsubscribe from optional communications by:</p>

    <ul>
        <li>Replying <b>STOP</b> to text messages.</li>
        <li>Using the unsubscribe instructions included in marketing emails.</li>';
        if ($phone !== '') {
            $printPage .= '<li>Calling us at '.$phone.'</li>';
        }
        if ($email !== '') {
            $printPage .= '<li>Emailing us at <a href="mailto:'.$email.'">'.$email.'</a></li>';
        }
    $printPage .= '
    </ul>';

$num++;
$printPage .= '
    <p><strong>'.$num.') SMS / MMS TEXT MESSAGING & MOBILE PRIVACY</strong></p>

    <p>'.$biz.' offers SMS/MMS messaging to communicate with customers and prospective customers about inquiries, appointments, services, and other authorized communications.</p>

    <p><b>How You Opt In:</b> You may opt in by submitting a website form with appropriate consent language, requesting text message updates, agreeing to messaging during a service interaction, or using another method that obtains the consent required for the messages being sent. Providing a mobile number solely to request service or an estimate does not automatically constitute consent to receive recurring or promotional text messages.</p>

    <p><b>Types of Messages:</b> Depending on your interactions and the programs to which you have consented, messages may include:</p>

    <ul>
        <li>Responses to inquiries and customer-support requests.</li>
        <li>Appointment confirmations, reminders, and scheduling updates.</li>
        <li>Service notifications and post-project follow-up communications.</li>
        <li>Customer satisfaction surveys, NPS requests, and feedback requests.</li>
        <li>Invitations to leave reviews on public platforms.</li>
        <li>Limited promotional messages related to our services, where permitted and with any required consent.</li>
    </ul>

    <p><b>Message frequency varies.</b> Message and data rates may apply. Consent to receive marketing or promotional text messages is not a condition of purchasing products or services.</p>

    <p><b>Opt-Out:</b> You may withdraw your consent by replying <b>STOP</b> to a text message or contacting us directly. We will honor valid opt-out requests as required by applicable law. You may receive a one-time confirmation message. If you participate in separately administered messaging programs, your opt-out may apply to the specific program identified in the confirmation, subject to applicable law and your other communication preferences.</p>

    <p><b>Help:</b> Reply <b>HELP</b> to a text message or contact us using the information at the end of this policy.</p>

    <p><b>Mobile Information Protection:</b> No mobile information will be shared with third parties or affiliates for their marketing or promotional purposes. We do not sell or rent mobile phone numbers, SMS opt-in information, messaging consent records, or opt-out information for independent marketing purposes.</p>

    <p>Mobile information may be disclosed to service providers that assist us in delivering text messages, operating messaging platforms, maintaining communication records, or processing opt-out requests, or when required by law. These providers are not authorized to use the information for their own independent marketing or promotional purposes.</p>

    <p><b>Carrier Disclosure:</b> Wireless carriers are not liable for delayed or undelivered messages. Message delivery may depend on carrier coverage, network availability, and technical conditions.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') POST-SERVICE SURVEYS & REVIEW REQUESTS</strong></p>

    <p>Following completion of a service or project, we may contact customers through authorized communication channels to request feedback about their experience.</p>

    <p>These communications may include customer satisfaction surveys, Net Promoter Score (NPS) requests, qualitative feedback, service follow-ups, and invitations to leave public reviews on third-party platforms.</p>

    <p>Participation is voluntary. Declining to complete a survey, provide feedback, or leave a public review will not affect your ability to receive services from us.</p>

    <p>Survey responses and feedback may be used internally to evaluate service quality, resolve customer concerns, and improve our operations. Reviews posted on public third-party platforms are subject to those platforms\' privacy practices.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') COOKIES, ANALYTICS & TRACKING</strong></p>

    <p>We use cookies and similar technologies to support website functionality, understand visitor behavior, improve user experience, and measure performance.</p>

    <p>Cookies are small files stored on your device. Some expire when your browsing session ends, while others remain until they expire or are deleted.</p>

    <p>Depending on the tools installed on our website, technologies may include:</p>

    <ul>
        <li><b>Essential Cookies:</b> Support website operation, security, and functionality.</li>
        <li><b>Analytics:</b> Tools such as Google Analytics that measure visits, traffic sources, navigation, and visitor engagement.</li>
        <li><b>Advertising and Conversion Tracking:</b> Tools such as Google Ads, Meta Pixel, or similar technologies, where implemented, to measure campaign effectiveness and advertising activity.</li>
        <li><b>Call Tracking:</b> Technologies such as dynamic phone numbers, where implemented, to identify marketing sources and measure inquiries.</li>
        <li><b>Chat and Messaging:</b> Tools used to support customer communications and improve responsiveness.</li>
    </ul>

    <p>These technologies may use cookies, pixels, tags, local storage, or similar identifiers and may collect IP addresses, device information, browser characteristics, and website activity. Some third-party providers may collect information across websites or online services, subject to their privacy practices.</p>

    <p>We use Google Analytics where implemented to understand website usage. Google may process collected information in accordance with its own policies, including for advertising-related purposes depending on the settings and services used. Learn more at <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener noreferrer">Google\'s Privacy Page</a>.</p>

    <p><b>Managing Cookies:</b> You may block or delete cookies using your browser settings. If our website provides cookie preference controls, you may also use those tools. Disabling certain cookies may affect website functionality.</p>

    <p><b>Global Privacy Control:</b> Some browsers and extensions support the Global Privacy Control (GPC) signal. Where required by applicable law, we will process supported signals as requests to opt out of applicable data processing activities, including certain advertising-related sharing. Applicable technical controls must be supported by the website\'s actual configuration.</p>

    <p><b>Do Not Track:</b> Some browsers provide a Do Not Track setting. Because no universally adopted standard governs these signals, our website may not respond to all Do Not Track requests.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') SHARING & DISCLOSURE OF INFORMATION</strong></p>

    <p>We may share information in limited circumstances necessary to operate our business, deliver services, maintain our website, and comply with applicable law.</p>

    <p><b>Service Providers:</b> We may use third-party vendors providing website hosting, analytics, advertising measurement, call tracking, customer management, scheduling, payment processing, survey and review-request platforms, email delivery, SMS messaging, and customer communication services.</p>

    <p>These providers may receive information reasonably necessary to perform services on our behalf. We do not authorize them to use customer information for their own independent marketing purposes. Where applicable, their handling of personal information is governed by contractual confidentiality, security, and data-processing obligations.</p>

    <p><b>Mobile Data:</b> All categories of sharing described in this section exclude sharing mobile contact information and text messaging opt-in data with third parties or affiliates for their marketing or promotional purposes. Such information may be processed by vendors assisting with message delivery or disclosed when required by law.</p>

    <p><b>Legal and Safety:</b> We may disclose information when necessary to comply with applicable law, respond to lawful requests, enforce our policies, investigate fraud, address security incidents, or protect the rights, property, and safety of our business, customers, or others.</p>

    <p><b>Business Transfers:</b> If our business is involved in a merger, acquisition, financing, restructuring, or sale of assets, information may be transferred as part of that transaction, subject to applicable law and appropriate safeguards.</p>

    <p><b>Advertising Technologies:</b> Certain third-party analytics or advertising tools may collect information through cookies and similar technologies. Depending on applicable privacy law, some advertising-related disclosures may constitute regulated sharing or selling even when no payment is exchanged. Applicable privacy rights and choices are described below.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') DATA SECURITY</strong></p>

    <p>We use reasonable administrative, technical, and organizational safeguards designed to protect personal information from unauthorized access, misuse, disclosure, alteration, or destruction.</p>

    <p>However, no method of internet transmission or electronic storage is completely secure, and we cannot guarantee absolute security.</p>

    <p>Email, SMS/MMS, and website chat may not provide end-to-end encryption. Please avoid submitting passwords, sensitive financial information, or other highly confidential information through these channels unless a secure method has been provided.</p>

    <p>If you believe information submitted to us has been compromised, please contact us using the information below.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') DATA RETENTION</strong></p>

    <p>We retain personal information for as long as reasonably necessary to fulfill the purposes described in this policy, including providing services, maintaining business records, administering communication programs, managing surveys and review requests, resolving disputes, enforcing agreements, and meeting legal obligations.</p>

    <p>Retention periods may vary depending on the type of information, the purpose for which it was collected, and applicable legal requirements.</p>

    <p>When information is no longer reasonably necessary, we take appropriate steps to delete, deidentify, or securely dispose of it in accordance with our retention practices and applicable law.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') YOUR PRIVACY RIGHTS & CHOICES</strong></p>

    <p>Depending on your state of residence and applicable privacy laws, you may have certain rights concerning your personal information, including the right to:</p>

    <ul>
        <li>Confirm whether we collect or process personal information about you.</li>
        <li>Request access to certain personal information we maintain.</li>
        <li>Request correction of inaccurate information.</li>
        <li>Request deletion of eligible personal information, subject to applicable exceptions.</li>
        <li>Request a copy of certain information in a portable format.</li>
        <li>Opt out of certain targeted advertising, data sharing, sales, or covered profiling activities.</li>
        <li>Appeal certain privacy-related decisions where required by applicable law.</li>
    </ul>

    <p>The availability and scope of these rights vary by jurisdiction and may depend on whether the relevant privacy law applies to our business.</p>

    <p><b>Exercising Your Rights:</b> You may submit a privacy request using the contact information below. We may need to verify your identity before processing a request. Where permitted by law, an authorized agent may submit a request on your behalf.</p>

    <p>If an appeal right applies and your request is denied, you may contact us to request reconsideration and identify your communication as a privacy rights appeal.</p>

    <p>You may also manage cookies through browser settings, use any available website privacy controls, unsubscribe from marketing emails, or reply STOP to text messages.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') THIRD-PARTY LINKS</strong></p>

    <p>Our website may contain links to third-party websites, review platforms, financing providers, or other external services. This Privacy Policy applies to our website and the communications described here, not to independently operated third-party websites.</p>

    <p>We are not responsible for the content, security, or privacy practices of external websites. We encourage you to review their privacy policies before providing information.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') CHILDREN\'S PRIVACY</strong></p>

    <p>Our website and communication programs are intended for users 18 years of age or older. We do not knowingly collect personal information directly from children under 18.</p>

    <p>If you believe a child has submitted personal information through our website or messaging programs, please contact us so we can review the situation and take appropriate action, including deletion when warranted.</p>';

$num++;
$printPage .= '
    <p><strong>'.$num.') CHANGES TO THIS POLICY</strong></p>

    <p>We may update this Privacy Policy periodically to reflect changes in our services, technology, business practices, or applicable laws.</p>

    <p>When changes are made, we will update the Last Updated date on this page. Where legally required, we will provide additional notice or obtain consent before implementing certain changes.</p>';

if ($state !== '') {
    $num++;
    $printPage .= '
        <p><strong>'.$num.') GOVERNING LAW</strong></p>

        <p>This Privacy Policy shall be governed by the applicable laws of the State of '.$state.', except where other applicable privacy laws or mandatory legal protections require otherwise.</p>';
}

$num++;
$printPage .= '
    <p><strong>'.$num.') CONTACT</strong></p>

    <p>Questions about this Privacy Policy, your personal information, communication preferences, or privacy rights may be directed to us using the following methods:</p>

    <ul>';
        if ($phone !== '') {
            $printPage .= '<li>Call us at '.$phone.'</li>';
        }
        if ($email !== '') {
            $printPage .= '<li>Email us at <a href="mailto:'.$email.'">'.$email.'</a></li>';
        }
        $printPage .= '
        <li>Use our <a href="/contact">Contact</a> page.</li>
    </ul>

    <p>For text messaging assistance, reply <b>HELP</b>. To unsubscribe from text messages, reply <b>STOP</b>.</p>';

return $printPage;
?>