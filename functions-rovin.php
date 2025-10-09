<?php
/* Battle Plan Web Design Functions: Rovin specific websites
/*--------------------------------------------------------------
# Set Up Complaint Forwarder
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Set Up Complaint Forwarder
--------------------------------------------------------------*/

//Detect and Label Complaint Messages
add_action('wpcf7_before_send_mail', 'bp_label_complaint_messages', 15, 3);
function bp_label_complaint_messages($contact_form, &$abort, $submission) {

    if (!$submission) return;
    $data = $submission->get_posted_data();
    if (empty($data)) return;

    // Evaluate [_raw_user-subject] tag using CF7’s own parser
    if (!function_exists('wpcf7_mail_replace_tags')) return;
    $evaluated_subject = strtolower(trim(wpcf7_mail_replace_tags('[_raw_user-subject]', [], $submission)));

    // Only continue if subject label includes "complaint"
    if (stripos($evaluated_subject, 'complaint') === false) {
         emailMe('Complaint Debug', 'Not a complaint.<br>Evaluated subject: ' . htmlentities($evaluated_subject));
        return;
    }

    // Retrieve mail properties and modify message label
    $formMail = $contact_form->prop('mail');
    $body = $formMail['body'];

    // Replace <b>Message:</b> → <b>Complaint:</b> while preserving markup
    $body = preg_replace(
        '/(<b[^>]*>)\s*Message\s*(:)\s*(<\/b>)/i',
        '$1Complaint$2$3',
        $body
    );

    $formMail['body'] = $body;
    $contact_form->set_properties(['mail' => $formMail]);

    // Optional debug
     emailMe('Complaint Label Modifier', 'Complaint detected.<br><br>' . htmlentities($formMail['body']));
}


// Forward Complaints to Central Server
add_action('wpcf7_mail_sent', 'bp_forward_complaints_to_central');
function bp_forward_complaints_to_central($contact_form) {

    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $data = $submission->get_posted_data();
    if (empty($data)) return;

    // Re-evaluate subject to confirm complaint
    if (!function_exists('wpcf7_mail_replace_tags')) return;
    $evaluated_subject = strtolower(trim(wpcf7_mail_replace_tags('[_raw_user-subject]', [], $submission)));

    if (stripos($evaluated_subject, 'complaint') === false) {
        // Not a complaint form
        return;
    }

    // Collect complaint data
    $complaint = [
        'site'      => $_SERVER['HTTP_HOST'] ?? '',
        'name'      => $data['user-name'] ?? '',
        'email'     => $data['user-email'] ?? '',
        'phone'     => $data['user-phone'] ?? '',
        'message'   => $data['user-message'] ?? '',
        'page'      => $_SERVER['HTTP_REFERER'] ?? '',
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
        'timestamp' => time(),
    ];

    // Shared secret stored as site option
    $secret = get_site_option('bp_rovin_secret');
    if (empty($secret)) {
         emailMe('Complaint Forwarder Error', 'Missing shared secret');
        return;
    }

    // Build signature
    $sig = hash_hmac('sha256', json_encode($complaint), $secret);

    // Post to central endpoint
    $endpoint = 'https://rovininc.com/wp-json/complaints/v1/add';
    $response = wp_remote_post($endpoint, [
        'timeout' => 15,
        'blocking' => true,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $sig,
        ],
        'body' => wp_json_encode($complaint),
    ]);
emailMe('Complaint Forwarded Debug', print_r($response, true));
    // Optional debug
     emailMe('Complaint Forwarded', print_r($response, true));
}
