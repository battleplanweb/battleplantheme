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


// Customer feedback → MyRovin Site Pulse (Customer Feedback → Emails).
// Replaces the old rovininc.com complaint forwarder. The framework does the actual forwarding
// (bp_feedback_forward_submission in functions-forms.php); these filters just supply Rovin's settings,
// so other companies configure their own without touching framework code.
add_filter('bp_feedback_forward_url', function () {
    return (defined('BP_ROVIN_SURVEY_URL') ? BP_ROVIN_SURVEY_URL : 'https://rovin.work') . '/wp-json/site-pulse/v1/email';
});
add_filter('bp_feedback_forward_secret', function () {
    return get_site_option('bp_rovin_survey_secret'); // shared rovin.work cross-site secret
});
// The contact form's "What is this regarding?" select. Change if the field name differs on the form.
add_filter('bp_feedback_category_field', function () { return 'user-subject'; });
// Only these category selections forward into MyRovin (case-insensitive).
add_filter('bp_feedback_forward_categories', function () { return ['complaint', 'compliment', 'comment']; });
// Tag the row with the restaurant brand based on which site it came from.
add_filter('bp_feedback_brand', function ($brand, $ctx) {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (strpos($host, 'bubba') !== false) return "Bubba's";
    if (strpos($host, 'babe')  !== false) return "Babe's";
    return $brand;
}, 10, 2);


/*--------------------------------------------------------------
# Customer Survey Forwarder → MyRovin (Site Pulse)
--------------------------------------------------------------*/

// A survey form is recognized by its form_id ending in '-survey' (e.g. 'bubbas-survey').
function bp_rovin_is_survey($ctx) {
    return (bool) preg_match('/-survey$/', (string) ($ctx['form_id'] ?? ''));
}

// Map the survey's rating radios (user-speed, user-friendliness, …) to the canonical dimension
// keys MyRovin stores. Keep this in sync with sp_survey_dimensions() on the receiver.
function bp_rovin_survey_rating_map() {
    return [
        'user-speed'        => 'speed',
        'user-friendliness' => 'friendliness',
        'user-temperature'  => 'temperature',
        'user-flavor'       => 'flavor',
        'user-portion'      => 'portion',
        'user-cleanliness'  => 'cleanliness',
        'user-atmosphere'   => 'atmosphere',
        'user-convenience'  => 'convenience',
        'user-value'        => 'value',
        'user-impression'   => 'impression',
    ];
}

// Forward a survey submission into MyRovin's Site Pulse. Runs in before_send so it can set
// skip_send. If the survey LANDS in MyRovin → no email at all. If it does NOT land (secret not
// set yet, or MyRovin unreachable) → during roll-out we still send a failure copy by email, but
// ONLY to email@bp-webdev.com — the client and all other To/Cc/Bcc recipients are stripped so no
// one else ever receives a failed-import survey. (To switch to true email-never later, see the
// fallback block at the bottom.) Signs the EXACT bytes it posts: sig = hash_hmac('sha256',
// rawBody, secret), sent as Authorization: Bearer <sig> — the receiver recomputes over the raw body.
add_filter('bp_form_before_send', 'bp_rovin_forward_survey', 20, 2);
function bp_rovin_forward_survey($email, $ctx) {
    if (!bp_rovin_is_survey($ctx)) return $email;

    $f        = $ctx['fields'] ?? [];
    $secret   = get_site_option('bp_rovin_survey_secret');
    $imported = false;

    if (!empty($secret)) {
        $ratings = [];
        foreach (bp_rovin_survey_rating_map() as $field => $key) {
            if (isset($f[$field]) && $f[$field] !== '') $ratings[$key] = (int) $f[$field];
        }

        $payload = [
            'site'       => $_SERVER['HTTP_HOST']     ?? '',
            'location'   => $f['user-location']       ?? '',
            'name'       => $f['user-name']           ?? '',
            'email'      => $f['user-email']          ?? '',
            'phone'      => $f['user-phone']          ?? '',
            'address'    => $f['user-address']        ?? '',
            'city'       => $f['user-city']           ?? '',
            'state'      => $f['user-state']          ?? '',
            'zip'        => $f['user-zip']            ?? '',
            'experience' => $f['user-experience']     ?? '',
            'visit_date' => $f['user-date']           ?? '',
            'referral'   => $f['user-refer']          ?? '',
            'ratings'    => $ratings,
            'comments'   => $f['user-comments']       ?? '',
            'ip'         => $ctx['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
            'timestamp'  => time(),
        ];

        $raw = wp_json_encode($payload);
        $sig = hash_hmac('sha256', $raw, $secret);

        // MyRovin's URL; override per-environment with define('BP_ROVIN_SURVEY_URL', '…') if needed.
        $hub = defined('BP_ROVIN_SURVEY_URL') ? BP_ROVIN_SURVEY_URL : 'https://rovin.work';

        $res = wp_remote_post(rtrim($hub, '/') . '/wp-json/site-pulse/v1/survey', [
            'timeout'  => 15,
            'blocking' => true,
            'headers'  => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $sig,
            ],
            'body' => $raw,
        ]);

        $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        if ($code >= 200 && $code < 300) {
            $imported = true;
        } else {
            $err = is_wp_error($res) ? $res->get_error_message() : ('HTTP ' . $code);
            error_log('[bp_rovin_survey] forward FAILED (' . $err . ') form=' . ($ctx['form_id'] ?? '') . ' site=' . ($_SERVER['HTTP_HOST'] ?? '') . ' payload=' . $raw);
        }
    }

    if ($imported) {
        $email['skip_send'] = true; // landed in MyRovin → no email
        return $email;
    }

    // ── ROLL-OUT FALLBACK ───────────────────────────────────────────────────────────────────────
    // Did NOT import. Send a failure copy, but to email@bp-webdev.com ONLY — strip the client and
    // every other recipient so no one else sees it. (For true email-never later, replace this whole
    // block with: $email['skip_send'] = true;)
    $email['to']      = 'email@bp-webdev.com';
    $email['cc']      = '';
    $email['bcc']     = '';
    $email['subject'] = '[Survey import FAILED] ' . ($email['subject'] ?? 'Customer Survey');

    return $email;
}
