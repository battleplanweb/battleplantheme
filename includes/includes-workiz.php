<?php
/* Battle Plan Web Design — Workiz Integration
 *
 * Two independent flows share this file's API client:
 *   1. PUSH  (this file):  website form submission  ->  Workiz LEAD  (lead/create)
 *   2. PULL  (jobsite-geo): Workiz completed JOBS   ->  jobsite_geo posts
 *      That reverse flow lives in includes-jobsite-geo-api.php next to the HCP
 *      and Company Cam drivers (bp_run_workiz_sync), and calls the shared
 *      bp_workiz_get() helper defined below.
 *
 * Activation: install the `workiz` option (holds credentials). See the config
 * block at the bottom of this file. The form->lead push is gated on
 * $workiz['push_leads']; the jobsite pull is gated on jobsite_geo.fsm_brand.
 */

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shared API Client
# Flow 1 — Form submission -> Workiz Lead
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Shared API Client
--------------------------------------------------------------*/

/**
 * Workiz credentials, from the per-site `workiz` option.
 * Returns [ token, secret ] (both '' if unset).
 *
 * Auth model (confirmed against api.workiz.com):
 *   - the API TOKEN is embedded in the URL PATH:  /api/v1/{token}/{endpoint}
 *   - the API SECRET goes in the JSON BODY of POSTs as `auth_secret`
 */
function bp_workiz_creds() {
	$o = get_option('workiz');
	// Fallback: a jobsite-only site may keep the token in the jobsite_geo option
	// (mirrors where HCP/Company Cam store theirs). Secret still comes from `workiz`.
	$token  = $o['api_token']   ?? ( get_option('jobsite_geo')['token'] ?? '' );
	$secret = $o['auth_secret'] ?? '';
	return [ trim((string) $token), trim((string) $secret) ];
}

/** Base URL for a given endpoint, token baked into the path. */
function bp_workiz_url($endpoint, $token) {
	return 'https://api.workiz.com/api/v1/' . rawurlencode($token) . '/' . ltrim($endpoint, '/');
}

/**
 * GET a Workiz endpoint (e.g. 'job/all/', 'lead/all/', 'job/get/{uuid}/').
 * Returns the decoded response array, or a WP_Error.
 *
 * Workiz wraps list responses as { flag: bool, data: [...], code: int }.
 */
function bp_workiz_get($endpoint, array $params = []) {
	list($token) = bp_workiz_creds();
	if ($token === '') return new WP_Error('workiz_no_token', 'No Workiz API token configured.');

	$url = bp_workiz_url($endpoint, $token);
	if ($params) $url = add_query_arg($params, $url);

	$res = wp_remote_get($url, [ 'timeout' => 30 ]);
	if (is_wp_error($res)) return $res;

	$code = (int) wp_remote_retrieve_response_code($res);
	$body = json_decode(wp_remote_retrieve_body($res), true);

	if ($code < 200 || $code >= 300) {
		return new WP_Error('workiz_http_' . $code, 'Workiz GET ' . $endpoint . ' returned HTTP ' . $code, $body);
	}
	return is_array($body) ? $body : [];
}

/**
 * POST to a Workiz endpoint (e.g. 'lead/create/'). The API secret is injected
 * into the JSON body as `auth_secret`. Returns decoded array or WP_Error.
 */
function bp_workiz_post($endpoint, array $body = []) {
	list($token, $secret) = bp_workiz_creds();
	if ($token === '') return new WP_Error('workiz_no_token', 'No Workiz API token configured.');

	$body['auth_secret'] = $secret;

	$res = wp_remote_post(bp_workiz_url($endpoint, $token), [
		'timeout' => 30,
		'headers' => [ 'Content-Type' => 'application/json' ],
		'body'    => wp_json_encode($body),
	]);
	if (is_wp_error($res)) return $res;

	$code = (int) wp_remote_retrieve_response_code($res);
	$raw  = wp_remote_retrieve_body($res);
	$decoded = json_decode($raw, true);

	if ($code < 200 || $code >= 300) {
		// Return the RAW body as the error detail so the log shows Workiz's actual
		// message even when the 400 body isn't clean JSON.
		return new WP_Error('workiz_http_' . $code, 'Workiz POST ' . $endpoint . ' returned HTTP ' . $code . ' · body: ' . $raw, $raw);
	}
	return is_array($decoded) ? $decoded : [];
}


/*--------------------------------------------------------------
# Flow 1 — Form submission -> Workiz Lead
--------------------------------------------------------------*/

/**
 * After a form is delivered, mirror it into Workiz as a new lead.
 *
 * Fires on the framework's post-send action (functions-forms.php):
 *     do_action('bp_form_after_send', $email, $ctx, $sent)
 * $ctx = [ form_id, fields (name=>value), customer, recipient, spam, ... ].
 *
 * Gated so it only runs when: the `workiz` option enables push_leads, the send
 * succeeded, the submission wasn't flagged spam, and (optionally) the form id is
 * on the allow-list. Runs after the browser already got its response, so a slow
 * or failing Workiz call never blocks the visitor.
 */
add_action('bp_form_after_send', 'bp_workiz_push_lead', 20, 3);
function bp_workiz_push_lead($email, $ctx, $sent) {

	$workiz = get_option('workiz');
	$fid    = $ctx['form_id'] ?? '';

	if ( ! bp_module_on($workiz) ) { bp_workiz_log('skip: workiz module off · form=' . $fid); return; }
	if ( empty($workiz['push_leads']) || $workiz['push_leads'] === 'false' ) {
		bp_workiz_log('skip: push_leads not enabled in DB (value=' . var_export($workiz['push_leads'] ?? null, true) . ') · form=' . $fid);
		return;
	}

	// Skip spam-flagged submissions. Do NOT gate on $sent: the notification email
	// and the CRM lead are independent channels, so a Brevo/wp_mail hiccup must not
	// cost you the lead. We just note it and push anyway.
	if ( ! empty($ctx['spam']) ) { bp_workiz_log('skip: spam-flagged (' . $ctx['spam'] . ') · form=' . $fid); return; }
	if ( ! $sent ) bp_workiz_log('note: form email send returned FALSE — pushing lead anyway · form=' . $fid);

	$form_id = $fid;
	$fields  = $ctx['fields'] ?? [];

	// Optional per-site allow-list of form ids (comma-separated string or array).
	// Empty = every form pushes a lead.
	$only = $workiz['forms'] ?? '';
	if ( ! is_array($only) ) $only = array_filter(array_map('trim', explode(',', (string) $only)));
	if ( $only && ! in_array($form_id, $only, true) ) {
		bp_workiz_log('skip: form "' . $form_id . '" not in allow-list [' . implode(',', $only) . '] · db-forms=' . var_export($workiz['forms'] ?? null, true));
		return;
	}

	// --- Name: prefer explicit first/last fields (e.g. the membership form),
	// and fall back to splitting a combined "Full Name" field on the standard forms. ---
	$first = trim((string) ($fields['user-first-name'] ?? ''));
	$last  = trim((string) ($fields['user-last-name']  ?? ''));
	if ($first === '' && $last === '') {
		$full  = trim((string) ($fields['user-name'] ?? ''));
		$first = $full;
		if ($full !== '' && strpos($full, ' ') !== false) {
			$parts = preg_split('/\s+/', $full, 2);
			$first = $parts[0];
			$last  = $parts[1] ?? '';
		}
	}

	$customer = $ctx['customer'] ?? array();

	// --- Build the lead payload. Field names per Workiz lead/create. ---
	// A visitor may leave optional fields blank; we send what we have.
	$lead = array(
		// ISO 8601 UTC, matching Workiz's documented example ("2024-01-15T09:00:00Z").
		'LeadDateTime'=> gmdate('Y-m-d\TH:i:s\Z'),
		'FirstName'   => $first,
		'LastName'    => $last,
		'Company'     => (string) ($fields['user-company'] ?? ''),
		// Workiz wants bare digits (e.g. "6195555555"), not a formatted number.
		'Phone'       => preg_replace('/\D+/', '', (string) ($fields['user-phone'] ?? '')),
		'Email'       => (string) ($fields['user-email']   ?? ''),
		'Address'     => (string) ($fields['user-address'] ?? ''),
		'City'        => (string) ($fields['user-city']    ?? ''),
		'State'       => (string) ($fields['user-state']   ?? ($customer['state-abbr'] ?? '')),
		'PostalCode'  => (string) ($fields['user-zip']     ?? ''),
		'Country'     => 'US',
		// NB: JobSource / JobType / CreatedBy are omitted from the base payload. Workiz
		// validates each against values configured in the account (Job Sources, Job
		// Types, real users) and 400s on anything it doesn't recognize. Set them only to
		// values that exist in Workiz — e.g. the membership filter below adds a validated
		// JobType. LeadNotes is free text and always safe.
		'LeadNotes'   => bp_workiz_compose_lead_notes($ctx),
	);

	// Drop empties so we don't overwrite Workiz fields with blanks.
	$lead = array_filter($lead, fn($v) => $v !== '' && $v !== null);

	/**
	 * Let a site tune the exact payload / field names against its live Workiz
	 * account without touching the framework (e.g. rename JobSource, map a custom
	 * form field, hard-set a ServiceArea).
	 */
	$lead = apply_filters('bp_workiz_lead_payload', $lead, $ctx, $workiz);
	if ( empty($lead) || empty($lead['Phone']) && empty($lead['Email']) ) {
		// Nothing usable to key a lead on — skip rather than create a blank lead.
		return;
	}

	// $lead has no secret in it (auth_secret is added inside bp_workiz_post), so
	// it's safe to log. This gives us a findable artifact of exactly what we sent
	// and what Workiz answered — important while the field names are being verified.
	bp_workiz_log('lead/create attempt · form=' . $form_id, $lead);

	$result = bp_workiz_post('lead/create/', $lead);

	if ( is_wp_error($result) ) {
		bp_workiz_log('lead/create FAILED · ' . $result->get_error_message(), $result->get_error_data());
		return;
	}

	// Workiz returns { flag: true, data: { UUID: ... } } on success.
	if ( empty($result['flag']) ) {
		bp_workiz_log('lead/create REJECTED by Workiz', $result);
		return;
	}

	bp_workiz_log('lead/create OK', $result);
	do_action('bp_workiz_lead_created', $result, $lead, $ctx);
}

/**
 * Append a line to wp-content/workiz-debug.log (and the PHP error log). Low-volume
 * (fires only on membership-style lead pushes), so it's safe to leave on; delete
 * the file anytime. Reveals the exact payload + Workiz response for troubleshooting.
 */
function bp_workiz_log($label, $data = null) {
	$line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $label;
	if ($data !== null) $line .= ' ' . (is_string($data) ? $data : wp_json_encode($data));
	@file_put_contents(WP_CONTENT_DIR . '/workiz-debug.log', $line . "\n", FILE_APPEND);
	error_log('[Workiz] ' . $label);
}

/**
 * Assemble the free-text note attached to the Workiz lead: the visitor's message
 * plus any extra posted fields that don't map to a first-class Workiz field, so
 * office staff see the full submission. Skips the framework/internal fields.
 */
function bp_workiz_compose_lead_notes($ctx) {

	$fields = $ctx['fields'] ?? array();

	$mapped = array('user-name','user-first-name','user-last-name','user-company','user-phone','user-email','user-address','user-city','user-state','user-zip','user-service');
	$skip   = array('bp_hp','bp_required','bp_form_token','bp_redirect','user-recipient-map');

	$lines = array();

	$msg = trim((string) ($fields['user-message'] ?? ''));
	if ($msg !== '') $lines[] = $msg;

	foreach ($fields as $k => $v) {
		if (in_array($k, $mapped, true) || in_array($k, $skip, true)) continue;
		if ($k === 'user-message') continue;
		if (is_array($v)) $v = implode(', ', $v);
		$v = trim((string) $v);
		if ($v === '') continue;
		$lines[] = bp_workiz_label_for($k) . ': ' . $v;
	}

	// Provenance footer so the office knows which site/page produced the lead.
	$src = $ctx['referrer'] ?? '';
	if ($src) $lines[] = 'Submitted from: ' . $src;

	return implode("\n", $lines);
}

/** Human label for a posted field slug in the lead note. A few known slugs get a
 *  hand-written label; everything else is title-cased ("user-pet-name" -> "Pet Name"). */
function bp_workiz_label_for($key) {
	$known = array(
		'user-equipment-brand' => 'Equipment Brand',
		'user-referral-source' => 'How they heard about us',
	);
	if (isset($known[$key])) return $known[$key];
	$key = preg_replace('/^user-/', '', $key);
	return ucwords(str_replace(array('-','_'), ' ', $key));
}


/*--------------------------------------------------------------
# Membership form ( [bp-membership-form] )
--------------------------------------------------------------*/

// The public "become a member" form. Separate First/Last name (Workiz wants them
// split), full mailing address, and required email + phone. On submit it goes
// through the normal bp form pipeline (spam checks, email to the client) AND
// bp_workiz_push_lead() forwards it into Workiz as a lead. form_id = 'membership'
// is what tags the lead's CreatedBy below.
add_shortcode('bp-membership-form', 'bp_workiz_membership_form');
function bp_workiz_membership_form() {

	// US states as a dropdown so Workiz gets a clean 2-letter State value.
	$states     = array('AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC');
	$state_opts = implode('|', $states);   // value === label (2-letter)

	// Pre-select the site's own state when we know it (memberships are usually local).
	$default_state = function_exists('customer_info') ? (customer_info()['state-abbr'] ?? '') : '';

	return do_shortcode('[bp-form id="membership" subject="New Membership Signup"]
		[layout grid="1-1"]
			[col][seek label="First Name" id="user-first-name" req="true"][bp-text name="user-first-name" required="true" autocomplete="given-name"][/seek][/col]
			[col][seek label="Last Name" id="user-last-name" req="true"][bp-text name="user-last-name" required="true" autocomplete="family-name"][/seek][/col]
		[/layout]
		[layout grid="1-1"]
			[col][seek label="Email" id="user-email" req="true"][bp-email name="user-email" required="true"][/seek][/col]
			[col][seek label="Phone" id="user-phone" req="true"][bp-tel name="user-phone" required="true"][/seek][/col]
		[/layout]
		[seek label="Company (optional)" id="user-company"][bp-text name="user-company" autocomplete="organization"][/seek]
		[seek label="Street Address" id="user-address" req="true"][bp-text name="user-address" required="true" autocomplete="street-address"][/seek]
		[layout grid="2-1-1"]
			[col][seek label="City" id="user-city" req="true"][bp-text name="user-city" required="true" autocomplete="address-level2"][/seek][/col]
			[col][seek label="State" id="user-state" req="true"][bp-select name="user-state" first="State" value="' . $default_state . '" options="' . $state_opts . '" required="true"][/seek][/col]
			[col][seek label="ZIP" id="user-zip" req="true"][bp-text name="user-zip" required="true" autocomplete="postal-code" maxlength="10"][/seek][/col]
		[/layout]
		[layout grid="1-1"]
			[col][seek label="Equipment Brand (optional)" id="user-equipment-brand"][bp-text name="user-equipment-brand"][/seek][/col]
			[col][seek label="How did you hear about us?" id="user-referral-source"][bp-select name="user-referral-source" first="— Select —" options="Google|Facebook|Referral from friend or family|Saw our truck or sign|Repeat customer|Other"][/seek][/col]
		[/layout]
		[seek label="Anything else we should know?" id="user-message"][bp-textarea name="user-message" rows="4"][/seek]
		[seek label="button"][bp-submit]Become a Member[/bp-submit][/seek]
	[/bp-form]');
}

// Membership-form leads are stamped with Job Type "Maintenance" (a Job Type that
// exists in the Workiz account, so it passes validation). Equipment brand + the
// customer's free-text answers ride along in LeadNotes (bp_workiz_compose_lead_notes).
// NB: JobType/JobSource/CreatedBy are all validated picklists — only set values that
// exist in Workiz, or the API 400s.
add_filter('bp_workiz_lead_payload', 'bp_workiz_tag_membership_lead', 10, 3);
function bp_workiz_tag_membership_lead($lead, $ctx, $workiz) {
	if (($ctx['form_id'] ?? '') === 'membership') {
		$lead['JobType'] = 'Maintenance';
	}
	return $lead;
}


/*--------------------------------------------------------------
# Config reference (paste into a site's functions-site.php)
----------------------------------------------------------------

	update_option('workiz', array(
		'install'     => 'true',
		'api_token'   => 'XXXXXXXXXXXXXXXX',   // Workiz API token
		'auth_secret' => 'XXXXXXXXXXXXXXXX',   // Workiz API secret (sent as auth_secret)
		'push_leads'  => 'true',               // Flow 1: form submissions -> Workiz leads
		'forms'       => '',                   // '' = all forms; or 'quote,contact' to limit
	));

	// Flow 2 (Workiz jobs -> jobsite_geo SEO pages) additionally needs, in the
	// existing jobsite_geo option:
	//     'fsm_brand' => 'Workiz',
	// The nightly cron then pulls completed jobs whose notes are wrapped in
	// ***  ...  *** (same editorial gate HCP and Company Cam use).

--------------------------------------------------------------*/
