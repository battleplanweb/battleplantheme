<?php
/* Battle Plan Web Design Functions: Forms

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Form Renderer ([bp-form])
# Field Shortcodes ([bp-text], [bp-email], etc.)
# Standard Forms ([bp-contact-form], [bp-quote-form])
# Multi-step wrapper ([bp-form-steps])
# REST Submission Endpoint
# Spam Pipeline
# Email Builder
# Helpers
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Form Renderer ([bp-form])
--------------------------------------------------------------*/

add_shortcode('bp-form', 'bp_render_form');
function bp_render_form($atts, $content = null) {
	$a = shortcode_atts([
		'id'         => 'contact',
		'class'      => '',
		'redirect'   => '/email-received/',
		'subject'    => '',
		'recipient'  => '',
	], $atts);

	$form_id   = sanitize_key($a['id']);
	$class     = trim('bp-form ' . esc_attr($a['class']));
	$redirect  = esc_attr($a['redirect']);
	$subject   = esc_attr($a['subject']);
	$recipient = esc_attr($a['recipient']);

	// Time-based HMAC for spam check (cache-friendly)
	$ts   = time();
	$host = $_SERVER['HTTP_HOST'] ?? 'bp';
	$hmac = substr(hash_hmac('sha256', (string)$ts, $host), 0, 12);

	bp_enqueue_script('battleplan-form', 'script-forms', []);

	// Render context — populated by child shortcodes during inner shortcode pass
	$GLOBALS['bp_form_render_ctx'] = ['id' => $form_id, 'accept' => [], 'max_mb' => 0, 'required' => []];

	$body = do_shortcode((string)$content);

	$apply_extra = apply_filters('bp_form_extra_fields', '', $form_id);
	if ($apply_extra) $body .= do_shortcode($apply_extra);

	$accept_exts     = $GLOBALS['bp_form_render_ctx']['accept'];
	$max_mb          = (int)$GLOBALS['bp_form_render_ctx']['max_mb'];
	$required_fields = $GLOBALS['bp_form_render_ctx']['required'];
	unset($GLOBALS['bp_form_render_ctx']);

	$out  = '<div class="' . $class . '" id="bpf-' . esc_attr($form_id) . '">';
	$out .= '<form class="bp-form-el init" enctype="multipart/form-data" data-form-id="' . esc_attr($form_id) . '">';

	// Honeypot + HMAC
	$out .= '<span class="bp-hp" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">';
	$out .= '<label>Leave this empty<input type="text" name="bp_hp" value="" tabindex="-1" autocomplete="off"></label>';
	$out .= '</span>';
	$out .= '<input type="hidden" name="bp_form" value="' . esc_attr($form_id) . '">';
	$out .= '<input type="hidden" name="bp_t" value="' . $ts . '">';
	$out .= '<input type="hidden" name="bp_h" value="' . $hmac . '">';
	$out .= '<input type="hidden" name="bp_redirect" value="' . $redirect . '">';
	if ($subject)   $out .= '<input type="hidden" name="bp_subject" value="'   . $subject   . '">';
	if ($recipient) $out .= '<input type="hidden" name="bp_recipient" value="' . $recipient . '">';

	// Sign the union of accept lists from [bp-file] children, so the server can
	// trust this form's declared allowlist without a per-site filter.
	if (!empty($accept_exts)) {
		$accept_exts = array_values(array_unique(array_map('strtolower', $accept_exts)));
		$accept_payload = base64_encode(wp_json_encode($accept_exts));
		$accept_hmac    = hash_hmac('sha256', $accept_payload, wp_salt('auth'));
		$out .= '<input type="hidden" name="bp_accept_types" value="' . esc_attr($accept_payload) . '">';
		$out .= '<input type="hidden" name="bp_accept_hmac"  value="' . esc_attr($accept_hmac) . '">';
	}

	// Sign the max-MB cap declared by [bp-file size="..."] children
	if ($max_mb > 0) {
		$size_payload = (string)$max_mb;
		$size_hmac    = hash_hmac('sha256', $size_payload, wp_salt('auth'));
		$out .= '<input type="hidden" name="bp_max_mb"      value="' . esc_attr($size_payload) . '">';
		$out .= '<input type="hidden" name="bp_max_mb_hmac" value="' . esc_attr($size_hmac) . '">';
	}

	// Sign the list of required field names declared by [bp-text required="true"]
	// etc., so the server can enforce them and reject direct POSTs that bypass
	// HTML5 validation by leaving required fields empty.
	if (!empty($required_fields)) {
		$required_fields   = array_values(array_unique($required_fields));
		$required_payload  = base64_encode(wp_json_encode($required_fields));
		$required_hmac     = hash_hmac('sha256', $required_payload, wp_salt('auth'));
		$out .= '<input type="hidden" name="bp_required"      value="' . esc_attr($required_payload) . '">';
		$out .= '<input type="hidden" name="bp_required_hmac" value="' . esc_attr($required_hmac) . '">';
	}

	$out .= $body;

	$out .= '<div class="bp-response" aria-live="polite" role="status"></div>';
	$out .= '</form>';
	$out .= '</div>';

	return $out;
}


/*--------------------------------------------------------------
# Field Shortcodes
--------------------------------------------------------------*/

function bp_field_attrs($a, $extra = []) {
	$attrs = [];
	if (!empty($a['name']))         $attrs[] = 'name="' . esc_attr($a['name']) . '"';
	if (!empty($a['id']))           $attrs[] = 'id="' . esc_attr($a['id']) . '"';
	if (!empty($a['placeholder']))  $attrs[] = 'placeholder="' . esc_attr($a['placeholder']) . '"';
	if (!empty($a['autocomplete'])) $attrs[] = 'autocomplete="' . esc_attr($a['autocomplete']) . '"';
	if (!empty($a['value']))        $attrs[] = 'value="' . esc_attr($a['value']) . '"';
	if (!empty($a['minlength']))    $attrs[] = 'minlength="' . (int)$a['minlength'] . '"';
	if (!empty($a['maxlength']))    $attrs[] = 'maxlength="' . (int)$a['maxlength'] . '"';
	if (!empty($a['pattern']))      $attrs[] = 'pattern="' . esc_attr($a['pattern']) . '"';
	if (!empty($a['required']) && $a['required'] !== 'false') {
		$attrs[] = 'required';
		bp_register_required($a['name'] ?? '');
	}
	foreach ($extra as $k => $v) $attrs[] = $k . '="' . esc_attr($v) . '"';
	return implode(' ', $attrs);
}

// Register a required field with the surrounding [bp-form], so the server-side
// handler can enforce it even when a bot bypasses HTML5 validation by POSTing
// directly. No-op when called outside a form-render pass.
function bp_register_required($name) {
	if (!isset($GLOBALS['bp_form_render_ctx'])) return;
	if (empty($name)) return;
	$base = preg_replace('/\[\]$/', '', $name);
	$GLOBALS['bp_form_render_ctx']['required'][] = $base;
}

function bp_field_wrap($name, $type, $inner) {
	return '<span class="bp-control-wrap" data-name="' . esc_attr($name) . '" data-type="' . esc_attr($type) . '">' . $inner . '</span>';
}

add_shortcode('bp-text', function($atts) {
	$a = shortcode_atts(['name'=>'', 'id'=>'', 'placeholder'=>'', 'autocomplete'=>'', 'value'=>'', 'required'=>'false', 'minlength'=>'', 'maxlength'=>'', 'pattern'=>'', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$cls = trim('bp-control bp-text ' . esc_attr($a['class']));
	return bp_field_wrap($a['name'], 'text', '<input type="text" class="' . $cls . '" ' . bp_field_attrs($a) . '>');
});

add_shortcode('bp-email', function($atts) {
	$a = shortcode_atts(['name'=>'user-email', 'id'=>'', 'placeholder'=>'', 'autocomplete'=>'email', 'value'=>'', 'required'=>'false', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$cls = trim('bp-control bp-email ' . esc_attr($a['class']));
	return bp_field_wrap($a['name'], 'email', '<input type="email" class="' . $cls . '" ' . bp_field_attrs($a) . '>');
});

add_shortcode('bp-tel', function($atts) {
	$a = shortcode_atts(['name'=>'user-phone', 'id'=>'', 'placeholder'=>'', 'autocomplete'=>'tel', 'value'=>'', 'required'=>'false', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$cls = trim('bp-control bp-tel ' . esc_attr($a['class']));
	return bp_field_wrap($a['name'], 'tel', '<input type="tel" class="' . $cls . '" ' . bp_field_attrs($a) . '>');
});

add_shortcode('bp-date', function($atts) {
	$a = shortcode_atts(['name'=>'', 'id'=>'', 'placeholder'=>'', 'value'=>'', 'required'=>'false', 'min'=>'', 'max'=>'', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$extra = [];
	if ($a['min']) $extra['min'] = $a['min'];
	if ($a['max']) $extra['max'] = $a['max'];
	$cls = trim('bp-control bp-date ' . esc_attr($a['class']));
	return bp_field_wrap($a['name'], 'date', '<input type="date" class="' . $cls . '" ' . bp_field_attrs($a, $extra) . '>');
});

add_shortcode('bp-number', function($atts) {
	$a = shortcode_atts(['name'=>'', 'id'=>'', 'placeholder'=>'', 'value'=>'', 'required'=>'false', 'min'=>'', 'max'=>'', 'step'=>'', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$extra = [];
	if ($a['min']  !== '') $extra['min']  = $a['min'];
	if ($a['max']  !== '') $extra['max']  = $a['max'];
	if ($a['step'] !== '') $extra['step'] = $a['step'];
	$cls = trim('bp-control bp-number ' . esc_attr($a['class']));
	return bp_field_wrap($a['name'], 'number', '<input type="number" class="' . $cls . '" ' . bp_field_attrs($a, $extra) . '>');
});

add_shortcode('bp-textarea', function($atts) {
	$a = shortcode_atts(['name'=>'user-message', 'id'=>'', 'placeholder'=>'', 'value'=>'', 'required'=>'false', 'rows'=>'5', 'cols'=>'', 'maxlength'=>'', 'minlength'=>'', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$value = $a['value'];
	$a['value'] = '';
	$cls = trim('bp-control bp-textarea ' . esc_attr($a['class']));
	$extra = ['rows' => (int)$a['rows']];
	if ($a['cols']) $extra['cols'] = (int)$a['cols'];
	$inner = '<textarea class="' . $cls . '" ' . bp_field_attrs($a, $extra) . '>' . esc_textarea($value) . '</textarea>';
	return bp_field_wrap($a['name'], 'textarea', $inner);
});

add_shortcode('bp-hidden', function($atts) {
	$a = shortcode_atts(['name'=>'', 'value'=>''], $atts);
	if (!$a['name']) return '';
	return '<input type="hidden" name="' . esc_attr($a['name']) . '" value="' . esc_attr($a['value']) . '">';
});

add_shortcode('bp-select', function($atts) {
	$a = shortcode_atts(['name'=>'', 'id'=>'', 'options'=>'', 'value'=>'', 'required'=>'false', 'first'=>'', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$opts = bp_parse_options($a['options']);
	$cls = trim('bp-control bp-select ' . esc_attr($a['class']));
	$attrs = [];
	if ($a['name'])     $attrs[] = 'name="' . esc_attr($a['name']) . '"';
	if ($a['id'])       $attrs[] = 'id="' . esc_attr($a['id']) . '"';
	if (!empty($a['required']) && $a['required'] !== 'false') {
		$attrs[] = 'required';
		bp_register_required($a['name']);
	}
	$inner = '<select class="' . $cls . '" ' . implode(' ', $attrs) . '>';
	if ($a['first'] !== '') $inner .= '<option value="">' . esc_html($a['first']) . '</option>';
	foreach ($opts as $opt) {
		$selected = ((string)$opt['value'] === (string)$a['value']) ? ' selected' : '';
		$inner .= '<option value="' . esc_attr($opt['value']) . '"' . $selected . '>' . esc_html($opt['label']) . '</option>';
	}
	$inner .= '</select>';
	return bp_field_wrap($a['name'], 'select', $inner);
});

add_shortcode('bp-radio', function($atts) {
	$a = shortcode_atts(['name'=>'', 'options'=>'', 'value'=>'', 'required'=>'false', 'class'=>''], $atts);
	$opts = bp_parse_options($a['options']);
	$cls  = trim('bp-radio ' . esc_attr($a['class']));
	$req  = (!empty($a['required']) && $a['required'] !== 'false');
	if ($req) bp_register_required($a['name']);
	$inner = '<span class="bp-list ' . $cls . '">';
	foreach ($opts as $i => $opt) {
		$id = sanitize_key($a['name']) . '-' . $i;
		$checked = ((string)$opt['value'] === (string)$a['value']) ? ' checked' : '';
		$inner .= '<span class="bp-list-item">';
		$inner .= '<label for="' . $id . '">';
		$inner .= '<input type="radio" name="' . esc_attr($a['name']) . '" id="' . $id . '" value="' . esc_attr($opt['value']) . '"' . ($req ? ' required' : '') . $checked . '>';
		$inner .= '<span class="bp-list-item-label">' . esc_html($opt['label']) . '</span>';
		$inner .= '</label>';
		$inner .= '</span>';
	}
	$inner .= '</span>';
	return bp_field_wrap($a['name'], 'radio', $inner);
});

add_shortcode('bp-checkbox', function($atts, $content = null) {
	$a = shortcode_atts(['name'=>'', 'value'=>'1', 'checked'=>'false', 'required'=>'false', 'label'=>'', 'class'=>''], $atts);
	$cls = trim('bp-control bp-checkbox ' . esc_attr($a['class']));
	$id  = sanitize_key($a['name']);
	$label = $a['label'] !== '' ? $a['label'] : (string)$content;
	$checked = ($a['checked'] !== 'false' && $a['checked'] !== '') ? ' checked' : '';
	$is_req  = (!empty($a['required']) && $a['required'] !== 'false');
	$req     = $is_req ? ' required' : '';
	if ($is_req) bp_register_required($a['name']);
	$inner = '<span class="bp-list-item">';
	$inner .= '<label for="' . $id . '">';
	$inner .= '<input type="checkbox" class="' . $cls . '" name="' . esc_attr($a['name']) . '" id="' . $id . '" value="' . esc_attr($a['value']) . '"' . $checked . $req . '>';
	if ($label !== '') $inner .= '<span class="bp-list-item-label">' . wp_kses_post($label) . '</span>';
	$inner .= '</label>';
	$inner .= '</span>';
	return bp_field_wrap($a['name'], 'checkbox', $inner);
});

add_shortcode('bp-checkboxes', function($atts) {
	$a = shortcode_atts(['name'=>'', 'options'=>'', 'value'=>'', 'class'=>''], $atts);
	$opts = bp_parse_options($a['options']);
	$cls  = trim('bp-checkboxes ' . esc_attr($a['class']));
	$selected = array_filter(array_map('trim', explode(',', $a['value'])));
	$base = preg_replace('/\[\]$/', '', $a['name']);
	$nameAttr = $base . '[]';
	$inner = '<span class="bp-list ' . $cls . '">';
	foreach ($opts as $i => $opt) {
		$id = sanitize_key($base) . '-' . $i;
		$checked = in_array((string)$opt['value'], $selected, true) ? ' checked' : '';
		$inner .= '<span class="bp-list-item">';
		$inner .= '<label for="' . $id . '">';
		$inner .= '<input type="checkbox" name="' . esc_attr($nameAttr) . '" id="' . $id . '" value="' . esc_attr($opt['value']) . '"' . $checked . '>';
		$inner .= '<span class="bp-list-item-label">' . esc_html($opt['label']) . '</span>';
		$inner .= '</label>';
		$inner .= '</span>';
	}
	$inner .= '</span>';
	return bp_field_wrap($base, 'checkboxes', $inner);
});

add_shortcode('bp-submit', function($atts, $content = null) {
	$a = shortcode_atts(['class'=>''], $atts);
	$cls = trim('bp-submit button ' . esc_attr($a['class']));
	$label = (string)$content !== '' ? wp_kses_post($content) : 'Send';
	$out  = '<button type="submit" class="' . $cls . '">' . $label . '</button>';
	$out .= '<span class="bp-spinner" aria-hidden="true"></span>';
	return $out;
});

// Default file types accepted by [bp-file] when no `accept` attr is given.
// Site-wide override: add_filter('bp_file_default_accept', fn() => 'jpg,png,pdf');
function bp_file_default_accept() {
	return apply_filters('bp_file_default_accept', 'jpg,jpeg,png,gif,webp,avif,heic,pdf,doc,docx,eps,tif,tiff');
}

add_shortcode('bp-file', function($atts) {
	$a = shortcode_atts(['name'=>'', 'id'=>'', 'accept'=>bp_file_default_accept(), 'size'=>'', 'required'=>'false', 'multiple'=>'false', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$cls = trim('bp-control bp-file ' . esc_attr($a['class']));
	$exts = array_filter(array_map(fn($e) => strtolower(trim($e, ". \t")), preg_split('/[\s,|]+/', $a['accept'])));
	$accept = !empty($exts) ? ' accept=".' . implode(',.', $exts) . '"' : '';
	// Register declared extensions and max size with the surrounding [bp-form]
	// so the server can use this form's own declaration as the trusted limits.
	if (isset($GLOBALS['bp_form_render_ctx'])) {
		if (!empty($exts)) {
			foreach ($exts as $ext) $GLOBALS['bp_form_render_ctx']['accept'][] = $ext;
		}
		$size_mb = (int)$a['size'];
		if ($size_mb > 0) {
			$current = $GLOBALS['bp_form_render_ctx']['max_mb'] ?? 0;
			$GLOBALS['bp_form_render_ctx']['max_mb'] = max($current, $size_mb);
		}
	}
	$is_req = (!empty($a['required']) && $a['required'] !== 'false');
	$req    = $is_req ? ' required' : '';
	$mul    = (!empty($a['multiple']) && $a['multiple'] !== 'false') ? ' multiple' : '';
	if ($is_req) bp_register_required($a['name']);
	$inner = '<input type="file" class="' . $cls . '" name="' . esc_attr($a['name']) . '" id="' . esc_attr($a['id']) . '"' . $accept . $req . $mul . '>';
	return bp_field_wrap($a['name'], 'file', $inner);
});

// Recipient selector — emits a select where each option's value is the LABEL,
// plus a hidden HMAC-signed map of label → email. Server resolves on submit.
// Options syntax: "Display Label::email@address.com | Other::other@..."
add_shortcode('bp-recipient-select', function($atts) {
	$a = shortcode_atts(['name'=>'user-recipient', 'id'=>'', 'options'=>'', 'value'=>'', 'required'=>'false', 'first'=>'', 'class'=>''], $atts);
	if (!$a['id']) $a['id'] = $a['name'];
	$cls = trim('bp-control bp-select bp-recipient-select ' . esc_attr($a['class']));

	// Parse "Label::email" pairs (label-first — different from bp-select)
	$pairs = [];
	foreach (preg_split('/\s*\|\s*/', trim((string)$a['options'])) as $part) {
		if (strpos($part, '::') === false) continue;
		[$label, $email] = array_map('trim', explode('::', $part, 2));
		if ($label === '' || $email === '') continue;
		$pairs[] = ['label' => $label, 'email' => $email];
	}

	$map = [];
	foreach ($pairs as $p) $map[$p['label']] = $p['email'];

	$payload = base64_encode(wp_json_encode($map));
	$hmac    = hash_hmac('sha256', $payload, wp_salt('auth'));

	$attrs = [];
	if ($a['name']) $attrs[] = 'name="' . esc_attr($a['name']) . '"';
	if ($a['id'])   $attrs[] = 'id="' . esc_attr($a['id']) . '"';
	if (!empty($a['required']) && $a['required'] !== 'false') {
		$attrs[] = 'required';
		bp_register_required($a['name']);
	}

	$inner  = '<select class="' . $cls . '" ' . implode(' ', $attrs) . '>';
	if ($a['first'] !== '') $inner .= '<option value="">' . esc_html($a['first']) . '</option>';
	foreach ($pairs as $p) {
		$selected = ((string)$p['label'] === (string)$a['value']) ? ' selected' : '';
		$inner .= '<option value="' . esc_attr($p['label']) . '"' . $selected . '>' . esc_html($p['label']) . '</option>';
	}
	$inner .= '</select>';
	$inner .= '<input type="hidden" name="bp_recipient_map"  value="' . esc_attr($payload) . '">';
	$inner .= '<input type="hidden" name="bp_recipient_hmac" value="' . esc_attr($hmac) . '">';
	$inner .= '<input type="hidden" name="bp_recipient_field" value="' . esc_attr($a['name']) . '">';

	return bp_field_wrap($a['name'], 'recipient-select', $inner);
});

function bp_parse_options($str) {
	$out = [];
	$str = trim((string)$str);
	if ($str === '') return $out;
	$parts = preg_split('/\s*\|\s*/', $str);
	foreach ($parts as $part) {
		if (strpos($part, '::') !== false) {
			[$value, $label] = array_map('trim', explode('::', $part, 2));
		} else {
			$value = $label = trim($part);
		}
		$out[] = ['value' => $value, 'label' => $label];
	}
	return $out;
}


/*--------------------------------------------------------------
# Standard Forms
--------------------------------------------------------------*/

add_shortcode('bp-contact-form', function($atts) {
	$a = shortcode_atts(['id'=>'contact', 'redirect'=>'', 'submit'=>'Send Message'], $atts);
	$body = apply_filters('bp_form_body', bp_default_contact_body($a['submit']), $a['id'], 'contact');
	// Only forward `redirect` to [bp-form] when the user explicitly set it,
	// so the [bp-form] default of /email-received/ kicks in otherwise.
	$redirect_attr = isset($atts['redirect']) ? ' redirect="' . esc_attr($a['redirect']) . '"' : '';
	return do_shortcode('[bp-form id="' . esc_attr($a['id']) . '"' . $redirect_attr . ']' . $body . '[/bp-form]');
});

add_shortcode('bp-quote-form', function($atts) {
	$a = shortcode_atts(['id'=>'quote', 'redirect'=>'', 'submit'=>'Request Quote'], $atts);
	$body = apply_filters('bp_form_body', bp_default_quote_body($a['submit']), $a['id'], 'quote');
	$redirect_attr = isset($atts['redirect']) ? ' redirect="' . esc_attr($a['redirect']) . '"' : '';
	return do_shortcode('[bp-form id="' . esc_attr($a['id']) . '"' . $redirect_attr . ']' . $body . '[/bp-form]');
});

function bp_default_contact_body($submit_label = 'Send Message') {
	return '
		[layout grid="3-3-2"]
			[col][seek label="Name" id="user-name" req="true"][bp-text name="user-name" required="true" autocomplete="name"][/seek][/col]
			[col][seek label="Email" id="user-email" req="true"][bp-email name="user-email" required="true"][/seek][/col]
			[col][seek label="Phone" id="user-phone" req="true"][bp-tel name="user-phone" required="true"][/seek][/col]
		[/layout]
		[layout]
			[col][seek label="Message" id="user-message" req="true" width="full"][bp-textarea name="user-message" required="true" rows="6"][/seek][/col]
		[/layout]
		[layout]
			[col][seek label="button"][bp-submit]' . esc_html($submit_label) . '[/bp-submit][/seek][/col]
		[/layout]
	';
}

function bp_default_quote_body($submit_label = 'Request Quote') {
	return '
		[layout]
			[col][seek label="Name" id="user-name" req="true"][bp-text name="user-name" required="true" autocomplete="name"][/seek][/col]
			[col][seek label="City" id="user-city"][bp-text name="user-city" autocomplete="address-level2"][/seek][/col]
			[col][seek label="Email" id="user-email" req="true"][bp-email name="user-email" required="true"][/seek][/col]
			[col][seek label="Phone" id="user-phone" req="true"][bp-tel name="user-phone" required="true"][/seek][/col]
		[/layout]
		[layout]
			[col class="form-stacked"][seek label="How can we help?" id="user-message" req="true"][bp-text name="user-message" required="true"][/seek][/col]
			[col][seek label="button"][bp-submit]' . esc_html($submit_label) . '[/bp-submit][/seek][/col]
		[/layout]
	';
}


/*--------------------------------------------------------------
# Multi-step wrapper ([bp-form-steps])
--------------------------------------------------------------*/

add_shortcode('bp-form-steps', 'bp_form_steps');
function bp_form_steps($atts, $content = null) {
	bp_enqueue_script('battleplan-form', 'script-forms', []);
	$inner = ($content !== null && $content !== '') ? $content : '';
	return '<div class="bp-form-steps" data-current="0">' . do_shortcode($inner) . '</div>';
}


/*--------------------------------------------------------------
# REST Submission Endpoint
--------------------------------------------------------------*/

add_action('rest_api_init', function() {
	register_rest_route('bp/v1', '/contact', [
		'methods'             => 'POST',
		'callback'            => 'bp_handle_form_submission',
		'permission_callback' => '__return_true', // we use our own HMAC verification
	]);
});

// Lightweight debug log writer for form submissions. Off by default — enable by
// defining `BP_FORM_DEBUG` true in wp-config.php, or appending `?bp_form_debug=1`
// to the page URL when submitting. Writes to wp-content/debug-bp-form.log,
// falls back to PHP error_log if that's not writable.
function bp_form_debug($msg) {
	$enabled = (defined('BP_FORM_DEBUG') && BP_FORM_DEBUG)
		|| (isset($_GET['bp_form_debug']) && $_GET['bp_form_debug'] === '1')
		|| (isset($_REQUEST['bp_form_debug']) && $_REQUEST['bp_form_debug'] === '1');
	if (!$enabled) return;
	$line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
	$log  = WP_CONTENT_DIR . '/debug-bp-form.log';
	if (@file_put_contents($log, $line, FILE_APPEND) === false) {
		error_log('[bp-form] ' . $msg);
	}
}

function bp_handle_form_submission(WP_REST_Request $request) {
	$params  = $request->get_params();
	$form_id = sanitize_key($params['bp_form'] ?? 'contact');

	bp_form_debug('--- submission start --- form=' . $form_id . ', files=' . count($request->get_file_params()));

	// --- HMAC + honeypot + speed check ---
	$hp     = isset($params['bp_hp']) ? trim((string)$params['bp_hp']) : '';
	$ts     = isset($params['bp_t'])  ? (int)$params['bp_t'] : 0;
	$hv     = isset($params['bp_h'])  ? (string)$params['bp_h'] : '';
	$host   = $_SERVER['HTTP_HOST'] ?? 'bp';
	$expect = substr(hash_hmac('sha256', (string)$ts, $host), 0, 12);
	$now    = time();

	$hash_ok  = ($ts > 0 && hash_equals($expect, $hv));
	$too_fast = ($ts > 0) ? (($now - $ts) < 5) : true;

	$spam_reason = '';
	if ($hp !== '')   $spam_reason = 'Bot:hp';
	elseif (!$hash_ok) $spam_reason = 'Bot:hash';
	elseif ($too_fast) $spam_reason = 'Bot:speed';

	// --- collect & sanitize submitted fields ---
	$fields = bp_collect_submitted_fields($params);

	// --- formatting (phone numbers) ---
	$fields = bp_format_field_values($fields);

	// --- required-field enforcement (server-side, mirrors HTML5 `required`) ---
	// Any field marked required="true" on the original form is listed here in a
	// signed payload. If a direct POST bypassed HTML5 validation and left any
	// required field empty, flag as Bot:incomplete and silently spam-route.
	if ($spam_reason === '') {
		$missing = bp_check_required_fields($params, $fields);
		if (!empty($missing)) $spam_reason = 'Bot:incomplete';
	}

	// --- file attachments ---
	$attach_result = bp_handle_form_attachments($form_id, $request->get_file_params(), $params);
	if (!empty($attach_result['errors']) && $spam_reason === '') {
		// Clean up any successfully moved files before erroring out
		foreach ($attach_result['attachments'] as $f) @unlink($f);
		return new WP_REST_Response([
			'status'  => 'validation_failed',
			'message' => implode(' ', $attach_result['errors']),
			'invalid' => $attach_result['invalid_fields'],
		], 200);
	}
	$attachments = $attach_result['attachments'];

	// Brevo (and some other transactional email APIs) strip modern image formats
	// silently. Convert WEBP/AVIF/HEIC/HEIF to JPG before they hit wp_mail.
	$attachments = bp_convert_attachments_for_email($attachments, $form_id);

	// --- validation (required, email/email-confirm) ---
	$validation = bp_validate_fields($fields);
	if (!$validation['ok']) {
		foreach ($attachments as $f) @unlink($f);
		return new WP_REST_Response([
			'status'  => 'validation_failed',
			'message' => $validation['message'],
			'invalid' => $validation['invalid'],
		], 200);
	}

	// --- resolve dynamic recipient (from [bp-recipient-select]) ---
	$customer_info = customer_info();
	$default_to    = bp_sanitize_email_list($params['bp_recipient'] ?? ($customer_info['email'] ?? get_option('admin_email')));
	if ($default_to === '') $default_to = sanitize_email(get_option('admin_email'));
	$resolved      = bp_resolve_recipient($params, $fields, $default_to);
	if ($resolved['tampered'] && $spam_reason === '') $spam_reason = 'Bot:recipient';

	// --- build context ---
	$ctx = [
		'form_id'        => $form_id,
		'fields'         => $fields,
		'customer'       => $customer_info,
		'recipient'      => $resolved['email'],
		'recipient_label'=> $resolved['label'],
		'subject'        => (string)($params['bp_subject'] ?? bp_default_subject($form_id, $customer_info)),
		'referrer'       => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
		'ip'             => $_SERVER['REMOTE_ADDR'] ?? '',
		'ua'             => $_SERVER['HTTP_USER_AGENT'] ?? '',
		'spam'           => $spam_reason,
		'attachments'    => $attachments,
	];

	// --- run spam pipeline (mutates $ctx['spam']) ---
	if ($spam_reason === '') $ctx = bp_spam_check($ctx);

	// --- build email ---
	$email = bp_build_email($ctx);
	$email['attachments'] = $attachments;

	// --- spam intercept: reroute to bp-webdev mailbox + log centrally ---
	if (!empty($ctx['spam'])) {
		$email['to']      = 'email@bp-webdev.com';
		$email['subject'] = '<- SPAM: Blocked ' . $ctx['spam'] . ' -> ' . $email['subject'];
		bp_central_ip_log($ctx['ip']);
	}

	// --- test-mode reroute: message starting with "test" goes only to the developer ---
	elseif (bp_is_test_submission($fields)) {
		$email['to']      = apply_filters('bp_form_test_recipient', 'glendon@bp-webdev.com');
		$email['subject'] = '<- TEST -> ' . $email['subject'];
		$email['cc']      = '';
		$email['bcc']     = '';
	}

	// --- pre-send hook (e.g. functions-rovin.php complaint labelling) ---
	$email = apply_filters('bp_form_before_send', $email, $ctx);

	// --- send ---
	$headers = ['Content-Type: text/html; charset=UTF-8'];
	if (!empty($email['from']))     $headers[] = 'From: '     . $email['from'];
	if (!empty($email['reply_to'])) $headers[] = 'Reply-To: ' . $email['reply_to'];
	if (!empty($email['cc']))       $headers[] = 'Cc: '       . $email['cc'];
	if (!empty($email['bcc']))      $headers[] = 'Bcc: '      . $email['bcc'];

	// Defer attachment cleanup to shutdown so SMTP/Brevo handlers that read file
	// contents during their own send hooks (after wp_mail returns) still find them.
	if (!empty($attachments)) {
		register_shutdown_function(function() use ($attachments) {
			foreach ($attachments as $f) {
				if (file_exists($f)) @unlink($f);
			}
		});
	}

	bp_form_debug('wp_mail call: to=' . $email['to'] . ', subject=' . $email['subject'] . ', attachments=' . count($email['attachments'] ?? []));
	foreach (($email['attachments'] ?? []) as $f) {
		bp_form_debug('  attachment path: ' . $f . ' (exists=' . (file_exists($f) ? 'yes' : 'NO') . ', readable=' . (is_readable($f) ? 'yes' : 'NO') . ', size=' . (file_exists($f) ? filesize($f) : 'n/a') . ')');
	}

	$sent = wp_mail($email['to'], $email['subject'], $email['body'], $headers, $email['attachments'] ?? []);

	bp_form_debug('wp_mail returned: ' . ($sent ? 'TRUE' : 'FALSE'));

	// --- post-send hook ---
	do_action('bp_form_after_send', $email, $ctx, $sent);

	if (!$sent) {
		return new WP_REST_Response([
			'status'  => 'mail_failed',
			'message' => 'Sorry, your message could not be sent right now. Please try again or call us.',
		], 200);
	}

	return new WP_REST_Response([
		'status'   => 'mail_sent',
		'message'  => 'Thank you. Your message has been sent.',
		'redirect' => esc_url_raw($params['bp_redirect'] ?? ''),
	], 200);
}

// Sanitize a recipient string that may contain one or more comma-separated
// email addresses. Each address is sanitized independently; invalid ones
// are dropped silently. sanitize_email() alone collapses commas/spaces and
// produces "a@x.comb@y.com", so it's only safe for single addresses.
function bp_sanitize_email_list($raw) {
	$valid = [];
	foreach (explode(',', (string)$raw) as $addr) {
		$clean = sanitize_email(trim($addr));
		if ($clean && is_email($clean)) $valid[] = $clean;
	}
	return implode(', ', $valid);
}

// Resolve recipient from a [bp-recipient-select] field, verifying the HMAC-signed map.
// Returns ['email' => $to, 'label' => $label, 'tampered' => bool].
function bp_resolve_recipient($params, $fields, $default_to) {
	$payload = $params['bp_recipient_map']   ?? '';
	$hmac    = $params['bp_recipient_hmac']  ?? '';
	$field   = $params['bp_recipient_field'] ?? '';

	if ($payload === '' || $hmac === '' || $field === '') {
		return ['email' => $default_to, 'label' => '', 'tampered' => false];
	}

	$expected = hash_hmac('sha256', $payload, wp_salt('auth'));
	if (!hash_equals($expected, $hmac)) {
		return ['email' => $default_to, 'label' => '', 'tampered' => true];
	}

	$map = json_decode(base64_decode($payload), true);
	if (!is_array($map)) {
		return ['email' => $default_to, 'label' => '', 'tampered' => true];
	}

	$picked = $fields[$field] ?? '';
	if ($picked === '' || !isset($map[$picked])) {
		return ['email' => $default_to, 'label' => '', 'tampered' => false];
	}

	// Allow comma-separated emails in the option value so a single dropdown
	// choice can route to multiple recipients. Each address is sanitized
	// independently; invalid ones are dropped.
	$valid = bp_sanitize_email_list($map[$picked]);
	if ($valid === '') {
		return ['email' => $default_to, 'label' => '', 'tampered' => true];
	}

	return ['email' => $valid, 'label' => $picked, 'tampered' => false];
}

// Process uploaded files: validate type/size, move to temp dir, return paths.
function bp_handle_form_attachments($form_id, $files, $params = []) {
	$out = ['attachments' => [], 'errors' => [], 'invalid_fields' => []];
	bp_form_debug('attach: handler called with ' . count($files) . ' file field(s)');
	if (empty($files)) {
		bp_form_debug('attach: no files to process — exiting');
		return $out;
	}

	$allowed = bp_resolve_attachment_allowlist($form_id, $params);
	if (empty($allowed)) {
		$out['errors'][] = 'File uploads are not enabled for this form.';
		return $out;
	}

	$max_mb    = bp_resolve_attachment_size_cap($form_id, $params);
	$max_bytes = $max_mb * 1024 * 1024;

	$upload = wp_upload_dir();
	$tmpdir = trailingslashit($upload['basedir']) . 'bp-form-tmp';
	if (!wp_mkdir_p($tmpdir)) {
		$out['errors'][] = 'Could not create attachment temp directory.';
		return $out;
	}

	// Normalize: REST may give a flat array per field, or HTML-multiple gives arrays of arrays
	foreach ($files as $field_name => $file) {
		$batch = bp_normalize_file_field($file);
		foreach ($batch as $f) {
			if (!isset($f['tmp_name']) || $f['error'] === UPLOAD_ERR_NO_FILE) continue;
			if ($f['error'] !== UPLOAD_ERR_OK) {
				$out['errors'][] = 'Upload error on ' . $field_name . '.';
				$out['invalid_fields'][] = $field_name;
				continue;
			}
			if ($f['size'] > $max_bytes) {
				$out['errors'][] = $field_name . ' exceeds ' . $max_mb . 'MB.';
				$out['invalid_fields'][] = $field_name;
				continue;
			}
			$check = wp_check_filetype_and_ext($f['tmp_name'], $f['name']);
			$ext = $check['ext'] ?: strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
			if (!in_array(strtolower($ext), $allowed, true)) {
				$out['errors'][] = $field_name . ' has a disallowed file type (' . esc_html($ext) . ').';
				$out['invalid_fields'][] = $field_name;
				continue;
			}
			$safe_name = wp_unique_filename($tmpdir, sanitize_file_name($f['name']));
			$dest = trailingslashit($tmpdir) . $safe_name;
			if (move_uploaded_file($f['tmp_name'], $dest)) {
				@chmod($dest, 0644);
				$out['attachments'][] = $dest;
				bp_form_debug('attach: moved ' . $f['name'] . ' (' . $f['size'] . ' bytes, ext=' . $ext . ') -> ' . $dest);
			} else {
				$out['errors'][] = 'Could not save ' . $field_name . '.';
				$out['invalid_fields'][] = $field_name;
				bp_form_debug('attach: move_uploaded_file FAILED for ' . $f['name'] . ' (tmp=' . $f['tmp_name'] . ', dest=' . $dest . ')');
			}
		}
	}

	return $out;
}

// Resolve the file-type allowlist for a form submission, in priority order:
//   1. Form-declared allowlist (HMAC-signed `bp_accept_types` from [bp-file accept="..."])
//   2. `bp_form_allowed_filetypes` filter override (per-site escape hatch)
//   3. Hard blocklist of executable/script types removed regardless of source
function bp_resolve_attachment_allowlist($form_id, $params) {
	$declared = null;

	// 1. Form-declared (signed at render, verified here)
	$payload = $params['bp_accept_types'] ?? '';
	$hmac    = $params['bp_accept_hmac']  ?? '';
	if ($payload !== '' && $hmac !== '') {
		$expected = hash_hmac('sha256', $payload, wp_salt('auth'));
		if (hash_equals($expected, $hmac)) {
			$decoded = json_decode(base64_decode($payload), true);
			if (is_array($decoded)) $declared = array_map('strtolower', $decoded);
		}
	}

	// 2. Filter — pass declared list as the default; site can extend, restrict, or replace
	$allowed = apply_filters('bp_form_allowed_filetypes', $declared ?? [], $form_id);
	$allowed = array_map('strtolower', (array)$allowed);

	// 3. Hard blocklist — never allow executable / script types, even if declared
	$blocked = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'pl', 'py', 'sh', 'cgi', 'exe', 'bat', 'cmd', 'msi', 'js', 'mjs', 'html', 'htm', 'svg'];
	$allowed = array_values(array_diff($allowed, $blocked));

	return $allowed;
}

// Convert image formats that transactional email providers (Brevo etc.) silently
// strip into JPG using WP's image editor (Imagick or GD). Fallback: keep original.
function bp_convert_attachments_for_email($attachments, $form_id) {
	$convert_exts = apply_filters('bp_form_convert_to_jpg', ['webp', 'avif', 'heic', 'heif'], $form_id);
	$convert_exts = array_map('strtolower', (array)$convert_exts);

	$out = [];
	foreach ($attachments as $path) {
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		if (!in_array($ext, $convert_exts, true)) { $out[] = $path; continue; }

		$editor = wp_get_image_editor($path);
		if (is_wp_error($editor)) {
			bp_form_debug('convert: wp_get_image_editor failed for ' . $path . ': ' . $editor->get_error_message());
			$out[] = $path;
			continue;
		}

		$jpg_path = preg_replace('/\.[^.]+$/', '.jpg', $path);
		if (file_exists($jpg_path)) {
			$jpg_path = preg_replace('/\.jpg$/', '-' . substr(md5($path . microtime()), 0, 6) . '.jpg', $jpg_path);
		}

		$saved = $editor->save($jpg_path, 'image/jpeg');
		if (is_wp_error($saved)) {
			bp_form_debug('convert: save failed for ' . $path . ': ' . $saved->get_error_message());
			$out[] = $path;
			continue;
		}

		@unlink($path);
		$final = $saved['path'] ?? $jpg_path;
		$out[] = $final;
		bp_form_debug('convert: ' . $ext . ' -> jpg: ' . basename($path) . ' => ' . basename($final));
	}
	return $out;
}

// Resolve max upload size in MB:
//   1. Form-declared `size="N"` on [bp-file] (HMAC-signed)
//   2. `bp_form_max_attachment_mb` filter override
//   3. Default 10MB
function bp_resolve_attachment_size_cap($form_id, $params) {
	$declared = null;
	$payload = $params['bp_max_mb']      ?? '';
	$hmac    = $params['bp_max_mb_hmac'] ?? '';
	if ($payload !== '' && $hmac !== '') {
		$expected = hash_hmac('sha256', $payload, wp_salt('auth'));
		if (hash_equals($expected, $hmac)) {
			$declared = (int)$payload;
		}
	}
	return (int) apply_filters('bp_form_max_attachment_mb', $declared ?? 10, $form_id);
}

function bp_normalize_file_field($file) {
	if (!is_array($file)) return [];
	if (!isset($file['name'])) return [];
	if (!is_array($file['name'])) return [$file];
	$out = [];
	$count = count($file['name']);
	for ($i = 0; $i < $count; $i++) {
		$out[] = [
			'name'     => $file['name'][$i]     ?? '',
			'type'     => $file['type'][$i]     ?? '',
			'tmp_name' => $file['tmp_name'][$i] ?? '',
			'error'    => $file['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
			'size'     => $file['size'][$i]     ?? 0,
		];
	}
	return $out;
}


/*--------------------------------------------------------------
# Spam Pipeline
--------------------------------------------------------------*/

function bp_spam_check($ctx) {
	$fields = $ctx['fields'];
	$name    = bp_pick_field($fields, 'name');
	$email   = bp_pick_field($fields, 'email');
	$phone   = bp_pick_field($fields, 'phone');
	$message = bp_pick_field($fields, 'message');

	$all = array_filter($fields, fn($v) => !empty($v) && !is_array($v));
	$full = implode(' ', $all);

	// in-message spam scrub (cleaned for kids / public-facing display only)
	$search_words  = ['fuck', 'shit', 'cunt', 'bitch'];
	$replace_words = ['####', '####', '####', '#####'];
	$message = is_string($message) ? str_replace($search_words, $replace_words, $message) : $message;
	if (is_string($message)) {
		foreach ($fields as $k => $v) {
			if (stripos($k, 'message') !== false) $ctx['fields'][$k] = $message;
		}
	}

	// Country block (non-US)
	$userCountry = $_COOKIE['user-country'] ?? '';
	if ($userCountry !== 'United States') {
		$site_name      = $ctx['customer']['name'] ?? '';
		$countryIgnore  = ["Chicken Dinner House", "Babe's Chicken Catering", "Sweetie Pie", "Cooks Country", "Rovin Inc"];
		$countryBlock   = ["Greater Fort Myers Dog Club"];
		$blockThis = false;

		foreach ($countryBlock as $site) {
			if (stripos($site_name, $site) !== false) { $blockThis = true; break; }
		}
		if ($message === '' || $message === null) $blockThis = true;
		foreach ($countryIgnore as $site) {
			if (stripos($site_name, $site) !== false) { $blockThis = false; break; }
		}
		if ($blockThis) { $ctx['spam'] = 'Country'; return $ctx; }
	}

	// Bad emails
	$bad_emails = bp_blocklist_emails();
	foreach ($bad_emails as $bad_email) {
		if ($email && stripos($email, $bad_email) !== false) { $ctx['spam'] = 'Email'; return $ctx; }
	}

	// Bad phones
	$bad_phones = ['0', '(0', '(11)'];
	foreach ($bad_phones as $bad_phone) {
		if ($phone && strpos($phone, $bad_phone) === 0) { $ctx['spam'] = 'Phone'; return $ctx; }
	}

	// Bad words
	$bad_words = bp_blocklist_words();
	foreach ($bad_words as $bad_word) {
		if (stripos($full, $bad_word) !== false) { $ctx['spam'] = 'Word'; return $ctx; }
	}

	// Kill words
	$bad_words = bp_blocklist_kill();
	foreach ($kill_words as $kill_word) {
		if (stripos($full, $kill_word) !== false) { $ctx['spam'] = 'Kill'; return $ctx; }
	}

	// Bad names
	$bad_names = ['loraine68', 'theron.bode46', 'brenden.lebsack'];
	foreach ($bad_names as $bad_name) {
		if ($name && stripos($name, $bad_name) !== false) { $ctx['spam'] = 'Name'; return $ctx; }
	}

	// Inline message-field checks (web addresses, repeated name as message)
	if (is_string($message) && is_string($name) && strtolower(trim($message)) === strtolower(trim($name))) {
		$ctx['spam'] = 'NameAsMessage';
		return $ctx;
	}
	$web_words = ['.com','http://','https://','.net','.org','www.','.buzz','bit.ly','ow.ly','t.co/','goo.gl'];
	if (is_string($message)) {
		foreach ($web_words as $w) {
			if (stripos($message, $w) !== false) { $ctx['spam'] = 'URL'; return $ctx; }
		}
	}

	// Bad phone numbers (in middle of phone string)
	if (is_string($phone) && stripos($phone, '1234567') !== false) { $ctx['spam'] = 'PhoneSeq'; return $ctx; }

	// Email/email-confirm mismatch
	$confirm = '';
	foreach ($fields as $k => $v) {
		if (stripos($k, 'email-confirm') !== false) { $confirm = $v; break; }
	}
	if ($confirm !== '' && $email !== $confirm) { $ctx['spam'] = 'EmailMismatch'; return $ctx; }

	// AI spam filter
	if (defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY) {
		$ai = bp_ai_spam_check($ctx);
		if ($ai === 'SPAM') $ctx['spam'] = 'AI';
	}

	return $ctx;
}

function bp_ai_spam_check($ctx) {
	$customer_info = $ctx['customer'];
	$name    = bp_pick_field($ctx['fields'], 'name');
	$email   = bp_pick_field($ctx['fields'], 'email');
	$phone   = bp_pick_field($ctx['fields'], 'phone');
	$message = bp_pick_field($ctx['fields'], 'message');
	$site_name   = $customer_info['name'] ?? get_bloginfo('name');
	$site_type   = $customer_info['type'] ?? 'local service business';
	$userCountry = $_COOKIE['user-country'] ?? '';

	$ai_prompt = "You are a spam filter for contact forms on local service business websites.\n\n"
		. "Reply with ONLY one word: SPAM or OK.\n\n"
		. "Business: {$site_name} ({$site_type})\n"
		. "Sender country: {$userCountry}\n"
		. "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\nMessage: {$message}\n\n"
		. "Mark SPAM if the submission is any of these:\n"
		. "- A cold sales pitch or marketing solicitation (SEO, ads, AI tools, software, lead gen, call answering services, virtual assistants, staffing)\n"
		. "- Business-to-business outreach where the sender is trying to SELL something to the business — even if framed as a partnership, collaboration, or revenue share\n"
		. "- A lead broker or lead generation company offering to sell leads, referrals, or homeowner bookings to this business\n"
		. "- A partnership or revenue-split proposal where the sender is not a customer but another business\n"
		. "- A generic cold-email opener (\"I came across your business\", \"I was researching [city] companies\", \"I noticed you're not showing up\", \"I have a few suggestions for your website\")\n"
		. "- Contains pricing for something being sold (\$X/mo, per lead pricing, free trial, no credit card needed, no contracts no commitments)\n"
		. "- Reputation management, web design, SEO, or digital marketing solicitation\n"
		. "- Bulk SMS / mass text campaigns (includes opt-out language like \"reply YES\", \"text STOP\", \"reply STOP to opt out\")\n"
		. "- Vague outreach with no specific service need — \"I have some suggestions\", \"I can help improve your...\", \"I'd love to connect\"\n"
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

	if (is_wp_error($ai_response) || wp_remote_retrieve_response_code($ai_response) !== 200) {
		if (!is_wp_error($ai_response) && function_exists('bp_ai_model_alert')) {
			$status = (int) wp_remote_retrieve_response_code($ai_response);
			bp_ai_model_alert($status, json_decode(wp_remote_retrieve_body($ai_response), true), 'claude-haiku-4-5-20251001', 'Form spam check');
		}
		return 'OK';
	}
	$ai_body    = json_decode(wp_remote_retrieve_body($ai_response), true);
	$ai_verdict = strtoupper(trim($ai_body['content'][0]['text'] ?? ''));
	return str_starts_with($ai_verdict, 'SPAM') ? 'SPAM' : 'OK';
}

function bp_central_ip_log($ip) {
	$central = 'https://bp-webdev.com/wp-content/email-add-ip.php';
	$secret  = 'Vn8qkM2Z4yHsR1jPwA3tLf7bE6uXpD9c';

	$site = $_SERVER['HTTP_HOST']      ?? '';
	$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
	$uri  = $_SERVER['REQUEST_URI']    ?? '';
	$ref  = $_SERVER['HTTP_REFERER']   ?? '';
	$ts   = (string) time();

	$payload = $ip . '|' . $site . '|' . $uri . '|' . $ua . '|' . $ref . '|' . $ts;
	$sig     = hash_hmac('sha256', $payload, $secret);

	wp_remote_post($central, [
		'timeout'     => 2,
		'redirection' => 0,
		'blocking'    => false,
		'body'        => [
			'ip'   => $ip,
			'site' => $site,
			'uri'  => $uri,
			'ua'   => $ua,
			'ref'  => $ref,
			'ts'   => $ts,
			'sig'  => $sig,
		],
	]);
}


/*--------------------------------------------------------------
# Email Builder
--------------------------------------------------------------*/

function bp_build_email($ctx) {
	$customer_info = $ctx['customer'];
	$fields        = $ctx['fields'];

	$site_name = $customer_info['name'] ?? get_bloginfo('name');
	$host      = $_SERVER['HTTP_HOST']  ?? '';

	$user_name  = bp_pick_field($fields, 'name');
	$user_email = bp_pick_field($fields, 'email');

	$subject = $ctx['subject'];
	if ($user_name && strpos($subject, '[user-name]') === false) $subject .= ' · ' . $user_name;
	$subject = str_replace(['[user-name]', '[user-email]'], [$user_name, $user_email], $subject);

	// Template-based body if a per-form template is registered, else auto-layout
	$template = (string) apply_filters('bp_form_email_template', '', $ctx['form_id'] ?? '', $ctx);
	if (trim($template) !== '') {
		$body = bp_render_email_template($template, $ctx);
	} else {
		$body = bp_render_email_autolayout($ctx);
	}

	// Metadata footer
	$userLoc     = trim(($_COOKIE['user-city'] ?? '') . ', ' . ($_COOKIE['user-region'] ?? ''), ', ');
	$userViews   = $_COOKIE['pages-viewed'] ?? '';
	$userViews   = ($userViews === '1') ? '1 page' : ($userViews ? $userViews . ' pages' : '');
	$userAgent   = $ctx['ua'];
	$userDevice  = is_mobile() ? 'a mobile device' : 'a desktop';
	if (strpos($userAgent, 'Mac'))     $userDevice = 'a Mac';
	if (strpos($userAgent, 'iPod'))    $userDevice = 'an iPod';
	if (strpos($userAgent, 'iPad'))    $userDevice = 'an iPad';
	if (strpos($userAgent, 'iPhone'))  $userDevice = 'an iPhone';
	if (strpos($userAgent, 'Android')) $userDevice = 'an Android';
	$userSystem = '';
	if (strpos($userAgent, 'iOS'))     $userSystem = ' running iOS';
	if (strpos($userAgent, 'Windows')) $userSystem = ' running Windows';
	if (strpos($userAgent, 'Linux'))   $userSystem = ' running Linux';

	$pageTitle = '';
	if ($ctx['referrer']) $pageTitle = get_the_title(url_to_postid($ctx['referrer']));

	$body .= '<div style="line-height:1.5; border-top:1px solid #8a8a8a; color:#8a8a8a; margin-top:5em;"><p>';
	$body .= 'Sent from the <em>' . esc_html($pageTitle ?: $ctx['referrer']) . '</em> page on the ' . esc_html($site_name) . ' website.</p>';
	$body .= '<p>Sender viewed';
	if ($userViews) $body .= ' ' . esc_html($userViews);
	$body .= ' using ' . esc_html($userDevice . $userSystem);
	if ($userLoc !== '' && $userLoc !== ',') $body .= ' near ' . esc_html($userLoc);
	$body .= '.<br>';
	$body .= '<em>Sender IP:</em> <a style="text-decoration:none; color:#8a8a8a;" href="https://whatismyipaddress.com/ip/' . esc_attr($ctx['ip']) . '">' . esc_html($ctx['ip']) . '</a>';
	$body .= '</p></div>';

	// Default From / Reply-To matching prior CF7 setup
	$server_email = 'email@admin.' . str_replace('https://', '', get_bloginfo('url'));
	$from         = 'Website · ' . str_replace(',', '', $site_name) . ' <' . $server_email . '>';
	$reply_to     = $user_name && $user_email ? $user_name . ' <' . $user_email . '>' : '';
	$cc           = '';
	$bcc          = 'Website Administrator <email@bp-webdev.com>';

	return [
		'to'       => $ctx['recipient'],
		'subject'  => $subject,
		'body'     => $body,
		'from'     => $from,
		'reply_to' => $reply_to,
		'cc'       => $cc,
		'bcc'      => $bcc,
	];
}

function bp_default_subject($form_id, $customer_info) {
	// "Quote Request" for quote forms; everything else defaults to "Customer Contact".
	// Override per-form via subject="..." attr on [bp-form] when needed.
	return $form_id === 'quote' ? 'Quote Request' : 'Customer Contact';
}

function bp_subject_prefix($ctx) {
	$subject = $ctx['subject'];
	$pos = strpos($subject, ' · ');
	return $pos !== false ? substr($subject, 0, $pos) : $subject;
}

// Auto-layout body — used when no per-form template is registered.
// Iterates posted fields in submit order, label-value rows in a single column.
function bp_render_email_autolayout($ctx) {
	$fields = $ctx['fields'];

	$labels = [];
	foreach ($fields as $key => $value) {
		if ($value === '' || $value === null) continue;
		$labels[$key] = bp_label_for_field($key, $ctx['form_id'] ?? '');
	}
	$maxLength = 0;
	foreach ($labels as $label) if (strlen($label) > $maxLength) $maxLength = strlen($label);
	$colWidth = round($maxLength * 12);

	$body  = '<div style="line-height:1.5"><p><b style="font-size:130%">' . esc_html(bp_subject_prefix($ctx)) . '</b></p><p>';
	foreach ($fields as $key => $value) {
		if ($value === '' || $value === null) continue;
		$label = $labels[$key] ?? '';
		// Empty label = framework-internal field (bp_*, g-recaptcha-response, etc.).
		// Skip entirely so signed payloads/tokens never leak into the email body.
		if ($label === '') continue;
		$display = is_array($value) ? implode(', ', $value) : (string)$value;
		$display = nl2br(esc_html($display));
		$body .= '<span style="display:inline-block; width:' . $colWidth . 'px; font-size:87%"><em><b>' . esc_html($label) . ':</b></em></span>';
		$body .= '<span>' . $display . '</span><br>';
	}
	$body .= '</p></div>';
	return $body;
}

// Template-based body — accepts a CF7-style template like:
//
//     Artist: [user-recipient]
//
//     Name: [user-name]
//     Phone: [user-phone]
//
//     Tattoo Location:
//     [user-location]
//
// Lines with `Label: [token]`  → two-column row (label + value)
// Lines with just text         → label-only row (full-width)
// Lines with just `[token]`    → value-only row (full-width)
// Blank lines                  → vertical spacing
function bp_render_email_template($template, $ctx) {
	$fields = $ctx['fields'];
	$lines  = preg_split('/\r\n|\n|\r/', $template);

	// First pass: figure out column width from longest label
	$maxLength = 0;
	foreach ($lines as $line) {
		$trimmed = trim($line);
		if ($trimmed === '') continue;
		$pos = strpos($trimmed, '[');
		if ($pos !== false && $pos > 0) {
			$labelPart = trim(substr($trimmed, 0, $pos));
			$labelPart = rtrim($labelPart, ':? ');
			$maxLength = max($maxLength, strlen($labelPart));
		}
	}
	$colWidth = round($maxLength * 12);

	$body = '<div style="line-height:1.5"><p><b style="font-size:130%">' . esc_html(bp_subject_prefix($ctx)) . '</b></p><p>';
	$lineCount = count($lines);
	for ($idx = 0; $idx < $lineCount; $idx++) {
		$rawLine = $lines[$idx];
		$line = trim($rawLine);
		if ($line === '') {
			$body .= '<br>';
			continue;
		}
		$pos = strpos($line, '[');
		if ($pos === false) {
			// Label-only line. Peek at the next non-blank line: if it's a value-only
			// `[token]` line that resolves to empty, drop the label too instead of
			// leaving an orphaned header like "Additional Info:" with nothing below.
			$dropLabel = false;
			for ($peek = $idx + 1; $peek < $lineCount; $peek++) {
				$nextTrim = trim($lines[$peek]);
				if ($nextTrim === '') continue;
				if (strpos($nextTrim, '[') === 0
					&& bp_resolve_template_tokens($nextTrim, $fields) === '') {
					$idx       = $peek; // consume the empty value line too
					$dropLabel = true;
				}
				break;
			}
			if ($dropLabel) continue;
			$body .= '<span style="display:inline-block; width:100%; font-size:87%"><em><b>' . esc_html($line) . '</b></em></span><br>';
			continue;
		}
		$labelPart = trim(substr($line, 0, $pos));
		$tokenPart = substr($line, $pos);
		$resolved  = bp_resolve_template_tokens($tokenPart, $fields);

		if ($labelPart !== '' && $resolved !== '') {
			$body .= '<span style="display:inline-block; width:' . $colWidth . 'px; font-size:87%"><em><b>' . esc_html($labelPart) . '</b></em></span>';
			$body .= '<span>' . $resolved . '</span><br>';
		} elseif ($labelPart !== '') {
			// Token rendered to nothing — drop the row entirely so empty fields don't leave a dangling label
			continue;
		} else {
			$body .= '<span>' . $resolved . '</span><br>';
		}
	}
	$body .= '</p></div>';
	return $body;
}

function bp_resolve_template_tokens($str, $fields) {
	// CF7-compat format token: [_format_field "format"] — runs the field value
	// through strtotime() + date($format). Lets templates write the date row as
	// `Date: [_format_user-date "D, M j, Y"]` so the renderer still treats the
	// line as a label/value pair (rather than baking the formatted date in as
	// literal text, which would turn it into a label-only line).
	$str = preg_replace_callback('/\[_format_([a-zA-Z0-9_\-]+)\s+"([^"]+)"\]/', function($m) use ($fields) {
		$key    = $m[1];
		$format = $m[2];
		if (!isset($fields[$key])) return '';
		$v = $fields[$key];
		if (is_array($v) || $v === '') return '';
		$ts = strtotime((string)$v);
		return $ts ? esc_html(date($format, $ts)) : esc_html((string)$v);
	}, $str);

	return preg_replace_callback('/\[([a-zA-Z0-9_\-]+)\]/', function($m) use ($fields) {
		$key = $m[1];
		// Allow [_raw_user-foo] → user-foo (CF7 raw-tag compat)
		if (strpos($key, '_raw_') === 0) $key = substr($key, 5);
		if (!isset($fields[$key])) return '';
		$v = $fields[$key];
		$v = is_array($v) ? implode(', ', $v) : (string)$v;
		return nl2br(esc_html($v));
	}, $str);
}

function bp_label_for_field($key, $form_id = '') {
	if (strpos($key, 'bp_') === 0) return '';
	if ($key === 'g-recaptcha-response') return '';

	$map = [
		'user-name'          => 'Name',
		'user-email'         => 'Email',
		'user-email-confirm' => 'Confirm Email',
		'user-phone'         => 'Phone',
		'user-message'       => 'Message',
		'user-subject'       => 'Subject',
		'user-city'          => 'City',
		'user-state'         => 'State',
		'user-zip'           => 'Zip',
		'user-address'       => 'Address',
		'user-business'      => 'Business',
		'user-position'      => 'Position',
		'user-service'       => 'Service',
		'user-date'          => 'Date',
		'user-time'          => 'Time',
		'user-contact'       => 'Preferred Contact',
		'user-comments'      => 'Comments',
		'user-recipient'     => 'Recipient',
	];
	$map = apply_filters('bp_field_labels', $map, $form_id);
	if (isset($map[$key])) return $map[$key];

	// derive from key
	$label = preg_replace('/^user-/', '', $key);
	$label = str_replace(['-', '_'], ' ', $label);
	return ucwords($label);
}


/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

function bp_collect_submitted_fields($params) {
	$out = [];
	foreach ($params as $key => $value) {
		// Framework-internal fields (honeypot, HMAC tokens, signed payloads for
		// recipient routing / file upload / required-field enforcement, etc.)
		// are all prefixed `bp_` — strip the whole namespace in one rule so a
		// new internal field added later doesn't leak into form output.
		if (strpos($key, 'bp_') === 0) continue;
		if ($key === 'rest_route') continue;
		if (is_array($value)) {
			$out[$key] = array_map(fn($v) => is_string($v) ? sanitize_textarea_field($v) : $v, $value);
		} elseif (is_string($value)) {
			$out[$key] = (stripos($key, 'email') !== false) ? sanitize_email(trim($value)) : sanitize_textarea_field(trim($value));
		}
	}
	return $out;
}

function bp_format_field_values($fields) {
	foreach ($fields as $key => $value) {
		if (!is_string($value)) continue;
		foreach (['phone', 'cell', 'mobile', 'fax'] as $t) {
			if (strpos($key, $t) !== false) {
				$fields[$key] = preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $value);
				break;
			}
		}
	}
	return $fields;
}

// Verify the signed `bp_required` payload from the form render, then check
// that each named field has a non-empty value in the submission. Returns the
// list of missing field names (empty array = all good).
function bp_check_required_fields($params, $fields) {
	$payload = (string)($params['bp_required']      ?? '');
	$hmac    = (string)($params['bp_required_hmac'] ?? '');
	if ($payload === '' || $hmac === '') return [];

	$expected = hash_hmac('sha256', $payload, wp_salt('auth'));
	if (!hash_equals($expected, $hmac)) return [];

	$required = json_decode((string)base64_decode($payload, true), true);
	if (!is_array($required)) return [];

	$missing = [];
	foreach ($required as $name) {
		$name = (string)$name;
		if ($name === '') continue;
		$val = $fields[$name] ?? null;
		if (is_array($val)) {
			$val = array_filter(array_map(fn($v) => trim((string)$v) !== '' ? $v : null, $val));
			if (empty($val)) $missing[] = $name;
		} else {
			if (trim((string)$val) === '') $missing[] = $name;
		}
	}
	return $missing;
}

function bp_validate_fields($fields) {
	$invalid = [];
	$msg = '';

	$email = '';
	$confirm = '';
	foreach ($fields as $key => $value) {
		if (stripos($key, 'email') !== false && stripos($key, 'confirm') === false) $email = $value;
		if (stripos($key, 'email-confirm') !== false) $confirm = $value;
	}
	if ($email && !is_email($email)) {
		$invalid[] = 'user-email';
		$msg = 'Please enter a valid email address.';
	}
	if ($confirm !== '' && $email !== $confirm) {
		$invalid[] = 'user-email-confirm';
		$msg = 'Are you sure this is the correct email?';
	}

	return ['ok' => empty($invalid), 'invalid' => $invalid, 'message' => $msg];
}

function bp_pick_field($fields, $keyword) {
	foreach ($fields as $k => $v) {
		if (stripos($k, $keyword) !== false && !empty($v) && stripos($k, 'confirm') === false) return $v;
	}
	return '';
}

// True if the user-message field starts with a word beginning with "test"
// (case-insensitive). Covers "Test", "Testing", "test the form", "tester", etc.
// Used to reroute the email to the developer instead of the client during testing.
function bp_is_test_submission($fields) {
	$message = bp_pick_field($fields, 'message');
	if (!is_string($message) || $message === '') return false;
	$first_word = strtok(ltrim($message), " \t\n\r\0\x0B");
	return $first_word !== false && stripos($first_word, 'test') === 0;
}

function bp_blocklist_emails() {
	return [$_SERVER['HTTP_HOST'] ?? '', 'testing.com', 'test@', 'b2blistbuilding', 'amy.wilsonmkt@gmail', 'agency.leads.fish', 'landrygeorge8@gmail', 'digitalconciergeservice', 'themerchantlendr', 'fluidbusinessresources', 'focal-pointcoaching.net', 'zionps', 'rddesignsllc', 'domainworld', 'marketing.ynsw@gmail', 'seoagetechnology@gmail', 'excitepreneur.net', 'bullmarket.biz', 'tworld', 'garywhi777@gmail', 'ronyisthebest16@gmail', 'ronythomas611@gmail', 'ronythomasrecruiter@gmail', 'ideonagency.net', 'axiarobbie20@gmail', 'hyper-tidy', 'readyjob.org', 'thefranchisecreatornetwork', 'franchisecreatormarketing', 'legendarygfx', 'hitachi-metal-jp', 'expresscommerce.co', 'zaphyrpro', 'erjconsult', 'christymkts@gmail', 'theheritageseo', 'freedomwebdesigns', 'wesavesmallbusinesses@gmail', 'bimservicesllc.net', 'spamhunter.co', 'myspamburner.co', 'spamshield.co', 'excelestimation.net', 'dmccreativesolutions', 'mdhmx', 'digitalmarketingvas', 'rushmoreblueprint.co', 'answeraide', 'servicesuite.io', 'webtechxpress', 'medicopostura', 'anna.cramer@outlook', 'stephania.sander@yahoo', 'yourmarketingagencyfuture@gmail', '.pawsafer.sale', 'wexinc', 'erjsolutions', 'frequentlyonline', 'thawkingo', 'podiatristusa', 'besocialworldwide', 'taylah.jordan@gmail', 'garzagaragedoors', 'westholtmed.net', 'agape1life', 'bayougraphics', 'betterfinancialsolution', 'betterbusinessedge', 'econnectlocal', 'sbiestimationll', 'zentrades.pro', 'appfactoryhub', 'caredogbest', 'w-bmason', 'vibrantestimation', 'tylersupplycompany', 'steinerseo', 'foxmail', 'posicionamientoparapymes', 'testeurpascher', 'sbi-estimation', 'est.sbiestimation', 'jebcapitalpartners', 'bestcontractorsites', 'secondestimationllc', 'ipayperlead', 'difusionagencia', 'seedranchflavor', 'grupoiasa', 'hedgestone', '6pmarketing', 'sowsustainability', 'xruma', 'businesscoachvas', 'costestimating', 'theubique-group', 'earnmillions', 'logodesignsteam', 'gracegroupsllc', 'rushmoreblueprintpartners', 'wiseins1', 'cleaning-dallas', 'financingmycustomers', 'innovenservices.com', 'ismael57morenozvm@outlook.com', 'vasdirect.com', 'webmai.co', 'hdsupply.com', 'automisly', 'flinnrgs32', '.co.uk', 'OYOapp.com', 'getoffyourhighhorse', 'advancedbodyscan.com', 'clientcaf.info', 'brucesilverman.outsourcing', 'chemtreat.com', 'astoundz.com', 'xinyisolar.online', 'BISHOPKNIGHTLLC.COM', 'rezult.org', 'casey.swiftt@aol.com', 'aecom-usa.com', 'academicproductions.com', 'houseflippers.biz', 'virtualhandsupport.com', 'pursuitind.com', 'magwitch.com', 'toptalentvas', 'mzfederal', 'moneysquad', 'dadknowsdiy.com', 'cahillestimating', 'bestaitools', 'dynamicvirtualmanager', 'expertcellent', 'dctechnolabs', 'ip-advocaat.com', 'bizbuydave', 'trustedvirtualteam', 'thevirtualsalesgroup', 'servicecallsaver', 'tile-stonecraetions', 'catehvac', 'bovafoodsco', 'usestateboilerinspector', 'kunal-kakkar', 'globalpartfinder', 'vladislavdev', 'frontierenergy', 'insuretuckertn', 'doanything', 'virtualeaseservice', 'humanwebdesign', 'dwbytes', 'onlinereviewfixer', 'archtri', 'aircoolsupply','restorecalls', 'businessbrokersleads', 'leaddesire', 'delphiconstructions', 'vettedvas', 'facilitiesfirstcall'];
}

function bp_blocklist_words() {
	return ['и', 'д', 'б', 'й', 'л', 'ы', 'З', 'у', 'Я', 'à', 'ô', 'ố', 'ế', 'á', 'ủ', 'ạ', '湖', '結', '衣', '市', '翼', '清', '水', 'http://', 'https://', 'www.', 'Dear Customer', 'Dear Sales', 'Sir/Madam', 'Sir/Madame', 'Hello Business Owner', 'HELLO SALES', 'bitcoin', 'mаlwаre', 'antivirus', 'marketing', 'SEO', 'Wordpress', 'Cost Estimation', 'Guarantee Estimation', 'World Wide Estimating', 'lead generation', 'completely Free', 'Dear Receptionist', 'Franchise Creator', 'rebrand your business', 'organic traffic', 'more business leads', 'We do Estimation', 'get your site published', 'high quality appointments and leads', 'new website', 'Google’s 1st Page', 'Does this sound interesting?', 'I notice that your website is very basic', 'appeal to more clients', 'improve your sales', 'free estimate from our company', 'blocks spam leads', 'block spam messages', 'In order to get a better idea of our work', 'cost estimates and take-off', 'If you\'ve made it this far', 'home services advertising' , 'cooperate with your company', 'influencers on Instagram', 'procuring below items', 'Optimizing your website', 'your website could be', 'blog posts they write', 'mobile app development', 'Your website could benefit', 'available for download', 'at no cost', 'boost your business', 'targeted Customers', 'We help you get', 'designing and development', 'create your Website' , 'contact form blasting', 'make money online', 'not an AI haha', 'send over the set of plans', 'audit on your website', 'audit on your site', 'Are you in need of', 'kingcontacts', 'suggestions for your site', 'Using Google Adsense', 'a few issues with your website', 'very profitable business matter', 'needs of business owners', 'According to the documents', 'Can you ship to Barbados', 'Freelance Web Designer', 'Our estimating services can help you', 'We have FREE opportunity', 'MyEListing', 'food packaging company', 'sexy pictures', 'Publicamos en periódicos', '1st page of Google', 'Need Accurate Estimate', 'Take-off Packages', 'data harvesting services', 'Accurate Quantity Take-offs', 'collaborate with your company', 'partner with you', 'business brokers that represent buyers', 'an official quotation', 'a company based in the Philippines', 'prefer not to hear from me again', 'short term investment', 'review provider', 'Myspace group', 'penning this article', 'abide by your requests', 'premium databases', 'Getting Reviews', 'estimating services', 'UncoverHiddenProfits', 'helping your business make money', 'find higher quality leads', 'estimating/architectural', 'opportunity to provide you', 'build business credit', 'eliminate personal guarantees', 'untangle my tax situation', 'exclusive sales training event', 'Are Your Hiring A Full Time HVAC Tech', 'less than perfect credit customers', 'Can you take on more clients', 'excellent option for prospective entrepreneurs', 'I noticed a few things that could use some fixing' , 'Kegel Devices', 'N/A', 'web development company', 'Mary Kay Sales Director', 'Odena', 'Kouvach', 'audit of your website', 'Ozempic', 'Wegovy', 'enhance your website', 'no-obligation proposal', 'summary of the audit results', 'XRumer 23 StrongAI', 'WhatsApp: +', 'Most Demanded AI Apps', 'possible acquire some Hose Pipes', 'Good Day, I am inquiring on', 'Good discount pricing will be appreciated', 'fix your forwarding system', 'Please advise on how soon order can be shipped out', 'fastest and most efficient way to destroy your wealth', 'do you have surcharges when making payment', 'Whatsapp', 'AUDIT ME, AMMAR', 'competitors are attracting clients online', 'handle more clients', 'I can miss a lot of emails from spam', 'Confidential – Please Forward to Owner', 'website placements', 'homepage that seems out of place', 'simple chatbot', 'guaranteed form submission', 'Good day and would like to know', 'gesture of goodwill', 'Reply YES', 'better website', 'increasing your organic leads', 'online visibility', 'saw a bug on your website', 'optimized website conversions', ' I can support your business', 'reduce their operating costs', 'unable to complete the checkout process', 'keyword-driven traffic', 'Buy exclusive repair leads', 'I help local businesses', 'top 3 map results', 'both need the right partner', 'Acquitrust Advisors', 'your clients', 'improve follow-up on missed calls', 'Want me to send over', 'targeted traffic', 'sourcing service quotations', 'top keywords', 'quantity takeoffs', 'cost planning', 'should show up first', 'Can I show you', 'AI employee', 'You pick the keywords', 'in front of hundreds', 'a few HVAC company owner', 'putting businesses like yours', 'advertising system', 'website traffic', 'update your keywords', 'live within 24 hours', 'Are you interested', 'would you take that spot', 'above the competition', 'position your brand', 'fix your website' ];
}

function bp_blocklist_kill() {
	return ['Get more reviews, Get more customers', 'We write the reviews', 'write an article', 'a free article', 'keyword targeted traffic', 'rank your google', 'boost your leads', 'write you an article', 'write a short article', 'website home page design', 'updated version of your website', 'free sample Home Page', 'what I would suggest for your website', 'improving your website', 'Would you be interested in an article', 'Do you need help with graphic design', 'I have an Audit of your website', 'Can we talk about your Website?', 'fix a few things on your website', 'warnings found on your website', 'offer some suggestions for your website', 'analyzed your website', 'He querido escribirte porque veo una excelente', 'enhance your online reputation', 'Supercharge your GMB listing', 'top pages of google', 'based in India', 'your website is in a great design', 'I apologize for my cold outreach', 'Our answering service frees you up', 'no-strings-attached call', 'local customers from your website', 'few opportunities to increase engagement', 'specialize in ad creatives', 'your site is absolutely outdated', 'This is not a sales pitch', 'help you hit the Top 3', 'qualified local leads', 'affecting your search rankings', 'top of search results', 'first in search results', 'targeted visitors', 'ahead of competitors', 'strong buyer interest', 'complimentary website previews', 'free website preview', 'costing you calls every week', 'your website could use some improvement', 'technical ranking errors', 'appears in AI search results', 'changing how customers find' ];
}

