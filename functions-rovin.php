<?php
/* Battle Plan Web Design Functions: Rovin specific websites
/*--------------------------------------------------------------
# Set Up Complaint Forwarder
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Set Up Complaint Forwarder
--------------------------------------------------------------*/

// Detect a complaint submission by checking for "complaint" in the user-subject field
function bp_rovin_is_complaint($ctx) {
    $subject = '';
    foreach ($ctx['fields'] ?? [] as $k => $v) {
        if (stripos($k, 'subject') !== false && is_string($v)) { $subject = $v; break; }
    }
    return stripos($subject, 'complaint') !== false;
}

// Detect and Label Complaint Messages — relabel "Message:" → "Complaint:" in the email body
add_filter('bp_form_before_send', 'bp_label_complaint_messages', 15, 2);
function bp_label_complaint_messages($email, $ctx) {
    if (!bp_rovin_is_complaint($ctx)) return $email;

    $email['body'] = preg_replace(
        '/(<b[^>]*>)\s*Message\s*(:)\s*(<\/b>)/i',
        '$1Complaint$2$3',
        $email['body']
    );
    return $email;
}


// Forward Complaints to Central Server
add_action('bp_form_after_send', 'bp_forward_complaints_to_central', 10, 3);
function bp_forward_complaints_to_central($email, $ctx, $sent) {
    if (!bp_rovin_is_complaint($ctx)) return;

    $fields = $ctx['fields'] ?? [];

    $complaint = [
        'site'      => $_SERVER['HTTP_HOST'] ?? '',
        'name'      => $fields['user-name']    ?? '',
        'email'     => $fields['user-email']   ?? '',
        'phone'     => $fields['user-phone']   ?? '',
        'message'   => $fields['user-message'] ?? '',
        'page'      => $_SERVER['HTTP_REFERER'] ?? '',
        'ip'        => $ctx['ip']               ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
        'timestamp' => time(),
    ];

    $secret = get_site_option('bp_rovin_secret');
    if (empty($secret)) return;

    $sig = hash_hmac('sha256', json_encode($complaint), $secret);

    wp_remote_post('https://rovininc.com/wp-json/complaints/v1/add', [
        'timeout'  => 15,
        'blocking' => true,
        'headers'  => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $sig,
        ],
        'body' => wp_json_encode($complaint),
    ]);
}
