<?php
/*--------------------------------------------------------------
# AI Alt Text Generation
----------------------------------------------------------------
Generates SEO-friendly, accessibility-grade alt text for image
attachments using Claude vision. Two entry points:

  - bp_ai_generate_alt_text($attachment_id) — call from anywhere
    (used by jobsite_geo auto-generation and the manual icon).
  - Sparkle icon in the Media Library "Alt Text" column — triggers
    AJAX → bp_ai_generate_alt_text → updates _wp_attachment_image_alt.

Requires constant ANTHROPIC_API_KEY OR BP_ANTHROPIC_API_KEY in
wp-config.php. If neither is defined, the helper no-ops and the
sparkle icon is suppressed.

Defaults:
  Model:        claude-haiku-4-5-20251001  (override: BP_AI_ALT_MODEL)
  Max tokens:   200                        (override: BP_AI_ALT_MAX_TOKENS)
--------------------------------------------------------------*/

if (!defined('BP_AI_ALT_MODEL'))      define('BP_AI_ALT_MODEL', 'claude-haiku-4-5-20251001');
if (!defined('BP_AI_ALT_MAX_TOKENS')) define('BP_AI_ALT_MAX_TOKENS', 200);

/**
 * Returns true if an Anthropic API key is configured.
 */
function bp_ai_alt_available() {
	return (defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY)
		|| (defined('BP_ANTHROPIC_API_KEY') && BP_ANTHROPIC_API_KEY);
}

function bp_ai_alt_api_key() {
	if (defined('BP_ANTHROPIC_API_KEY') && BP_ANTHROPIC_API_KEY) return BP_ANTHROPIC_API_KEY;
	if (defined('ANTHROPIC_API_KEY')    && ANTHROPIC_API_KEY)    return ANTHROPIC_API_KEY;
	return '';
}

/**
 * Generate alt text for an image attachment via Claude vision.
 * Returns the alt text string, or a WP_Error on failure.
 * Does NOT save — caller decides whether to update_post_meta.
 */
function bp_ai_generate_alt_text($attachment_id) {
	$attachment_id = (int) $attachment_id;
	if (!$attachment_id) return new WP_Error('bad_id', 'Invalid attachment ID.');

	if (!bp_ai_alt_available()) {
		return new WP_Error('no_api_key', 'No Anthropic API key configured.');
	}

	$mime = get_post_mime_type($attachment_id);
	if (!$mime || strpos($mime, 'image/') !== 0) {
		return new WP_Error('not_image', 'Attachment is not an image.');
	}

	// Read + base64-encode the image inline. URL-source would require the
	// Anthropic API to fetch the image directly, but WPE's firewall blocks
	// many external bots, so inline data is the reliable path.
	$image = bp_ai_alt_load_image_for_api($attachment_id);
	if (is_wp_error($image)) return $image;

	$customer = function_exists('customer_info') ? customer_info() : [];
	$prompt   = bp_ai_alt_build_prompt($attachment_id, $customer);

	$body = [
		'model'      => BP_AI_ALT_MODEL,
		'max_tokens' => BP_AI_ALT_MAX_TOKENS,
		'messages'   => [
			[
				'role'    => 'user',
				'content' => [
					[
						'type'   => 'image',
						'source' => [
							'type'       => 'base64',
							'media_type' => $image['mime'],
							'data'       => $image['data'],
						],
					],
					[
						'type' => 'text',
						'text' => $prompt,
					],
				],
			],
		],
	];

	$response = wp_remote_post('https://api.anthropic.com/v1/messages', [
		'timeout' => 30,
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => bp_ai_alt_api_key(),
			'anthropic-version' => '2023-06-01',
		],
		'body' => wp_json_encode($body),
	]);

	if (is_wp_error($response)) return $response;

	$status  = wp_remote_retrieve_response_code($response);
	$decoded = json_decode(wp_remote_retrieve_body($response), true);

	if ($status !== 200) {
		$msg = $decoded['error']['message'] ?? 'Unknown API error.';
		return new WP_Error('api_error', "Anthropic API error ($status): $msg");
	}

	$text = trim((string)($decoded['content'][0]['text'] ?? ''));
	if ($text === '') return new WP_Error('empty', 'Empty response from API.');

	// Strip wrapping quotes the model sometimes adds despite instructions,
	// and any leading "Alt text:" / "Alt:" prefix.
	$text = preg_replace('/^(alt text|alt)\s*:\s*/i', '', $text);
	$text = trim($text, "\"' \t\n");

	return $text;
}

/**
 * Read an attachment's image file and return [mime, base64 data] suitable
 * for inline submission to the Anthropic vision API. Downscales oversized
 * images (Claude's recommended max long-side is 1568px; we cap at that to
 * keep payload small and reduce input-token cost).
 */
function bp_ai_alt_load_image_for_api($attachment_id) {
	$file = get_attached_file($attachment_id);
	if (!$file || !file_exists($file)) {
		return new WP_Error('no_file', 'Image file missing on disk.');
	}

	$mime = (string) get_post_mime_type($attachment_id);
	$accepted = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

	// Claude's vision API supports JPEG/PNG/GIF/WebP. For anything else
	// (HEIC, AVIF, TIFF, etc.) re-encode via wp_get_image_editor to JPEG.
	// Same path also handles downscaling when the long side > 1568px.
	$needs_reencode = !in_array($mime, $accepted, true);

	$dims = @getimagesize($file);
	$w = is_array($dims) ? (int)($dims[0] ?? 0) : 0;
	$h = is_array($dims) ? (int)($dims[1] ?? 0) : 0;
	$needs_resize = $w && $h && max($w, $h) > 1568;

	if ($needs_reencode || $needs_resize) {
		$editor = wp_get_image_editor($file);
		if (is_wp_error($editor)) return $editor;

		if ($needs_resize) {
			$scale = 1568 / max($w, $h);
			$editor->resize((int) round($w * $scale), (int) round($h * $scale), false);
		}

		$out_mime = in_array($mime, $accepted, true) ? $mime : 'image/jpeg';
		$tmp = wp_tempnam('bp-ai-alt');
		$editor->set_quality(85);
		$saved = $editor->save($tmp, $out_mime);
		if (is_wp_error($saved)) {
			@unlink($tmp);
			return $saved;
		}

		$bytes = file_get_contents($saved['path']);
		@unlink($saved['path']);
		if ($bytes === false) return new WP_Error('read_fail', 'Could not read resized image.');

		return ['mime' => $out_mime, 'data' => base64_encode($bytes)];
	}

	$bytes = file_get_contents($file);
	if ($bytes === false) return new WP_Error('read_fail', 'Could not read image file.');

	return ['mime' => $mime, 'data' => base64_encode($bytes)];
}

/**
 * Build the Claude prompt — pulls in business context + the attachment's
 * parent post (for jobsite_geo this is the job title, etc.).
 */
function bp_ai_alt_build_prompt($attachment_id, $customer) {
	$biz_name  = $customer['name']        ?? '';
	$biz_type  = $customer['site-type']   ?? ($customer['business-type'] ?? '');
	$city      = $customer['city']        ?? '';
	$state     = $customer['state-abbr']  ?? ($customer['state-full'] ?? '');
	$services  = is_array($customer['service-type'] ?? null) ? implode(', ', $customer['service-type']) : '';

	$parent_id    = wp_get_post_parent_id($attachment_id);
	$parent_title = $parent_id ? get_the_title($parent_id) : '';
	$parent_type  = $parent_id ? get_post_type($parent_id) : '';
	$parent_excerpt = '';
	if ($parent_id) {
		$parent  = get_post($parent_id);
		$content = $parent ? wp_strip_all_tags((string)$parent->post_content) : '';
		$parent_excerpt = mb_substr(trim($content), 0, 400);
	}

	$lines = [];
	$lines[] = "You are writing accessible, SEO-aware alt text for an image on a business website.";
	$lines[] = "";
	if ($biz_name)   $lines[] = "Business: {$biz_name}" . ($biz_type ? " (a {$biz_type} business)" : "");
	if ($city || $state) {
		$loc = trim("{$city}" . ($city && $state ? ", " : "") . "{$state}");
		$lines[] = "Location: {$loc}";
	}
	if ($services)   $lines[] = "Services: {$services}";
	if ($parent_title) {
		$context_label = $parent_type === 'jobsite_geo' ? 'Jobsite' : ucfirst($parent_type ?: 'Page');
		$lines[] = "Attached to {$context_label}: \"{$parent_title}\"";
	}
	if ($parent_excerpt) $lines[] = "Context: {$parent_excerpt}";
	$lines[] = "";
	$lines[] = "Write ONE line of alt text describing what is concretely visible in the image.";
	$lines[] = "Requirements:";
	$lines[] = "- 80–125 characters";
	$lines[] = "- Reads naturally — NOT keyword-stuffed";
	$lines[] = "- Include location and service context only where it fits naturally";
	$lines[] = "- Describe specific objects, settings, actions you can see";
	$lines[] = "- Do NOT start with \"Image of\", \"Picture of\", \"Photo of\"";
	$lines[] = "- Do NOT wrap in quotes or add any prefix like \"Alt:\"";
	$lines[] = "";
	$lines[] = "Return ONLY the alt text. No commentary.";

	return implode("\n", $lines);
}

/*--------------------------------------------------------------
# Cron hook — async generation queued from save handlers
----------------------------------------------------------------
Generate alt text for an attachment in the background. WP-Cron
dedups by hook+args so scheduling the same {attachment_id} twice
in quick succession has no effect.
--------------------------------------------------------------*/

add_action('bp_ai_alt_generate_cron', function($attachment_id) {
	$alt = bp_ai_generate_alt_text((int)$attachment_id);
	if (is_wp_error($alt) || $alt === '') return;
	update_post_meta((int)$attachment_id, '_wp_attachment_image_alt', $alt);
});

/*--------------------------------------------------------------
# AJAX endpoint — manual icon trigger
--------------------------------------------------------------*/

add_action('wp_ajax_bp_ai_alt_generate', function() {
	check_ajax_referer('bp_ai_alt', 'nonce');

	if (!current_user_can('upload_files')) {
		wp_send_json_error(['message' => 'Not authorized.'], 403);
	}

	$id = isset($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : 0;
	if (!$id) wp_send_json_error(['message' => 'Missing attachment ID.'], 400);

	$alt = bp_ai_generate_alt_text($id);
	if (is_wp_error($alt)) {
		wp_send_json_error(['message' => $alt->get_error_message()], 500);
	}

	update_post_meta($id, '_wp_attachment_image_alt', $alt);
	wp_send_json_success(['alt' => $alt]);
});

/*--------------------------------------------------------------
# Admin UI — sparkle icon in Media Library list view
--------------------------------------------------------------*/

add_action('admin_enqueue_scripts', function($hook) {
	if ($hook !== 'upload.php') return;
	if (!bp_ai_alt_available()) return;

	$nonce = wp_create_nonce('bp_ai_alt');
	$js = <<<JS
(function(){
	const NONCE = '{$nonce}';
	const SPARKLE = '✨'; // ✨

	function getCol(tr) {
		return tr.querySelector('td.bp-alt-text, td.column-bp-alt-text');
	}
	function getPostId(tr) {
		// bp_admin_columns puts inline-edit spans with data-post-id; fall back to row id.
		const span = tr.querySelector('.bp-inline-edit[data-post-id]');
		if (span) return span.getAttribute('data-post-id');
		const m = (tr.id || '').match(/post-(\\d+)/);
		return m ? m[1] : null;
	}

	function addIcon(td) {
		if (!td || td.querySelector('.bp-ai-alt-btn')) return;
		const tr = td.closest('tr');
		const id = getPostId(tr);
		if (!id) return;

		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'button-link bp-ai-alt-btn';
		btn.title = 'Generate alt text with AI';
		btn.setAttribute('data-post-id', id);
		btn.style.cssText = 'color:#9b59b6; cursor:pointer; text-decoration:none; border:none; background:none; padding:0; margin-left:6px; font-size:14px;';
		btn.textContent = SPARKLE;
		td.appendChild(document.createTextNode(' '));
		td.appendChild(btn);
	}

	function scan() {
		document.querySelectorAll('tr').forEach(function(tr){
			addIcon(getCol(tr));
		});
	}

	async function generate(btn) {
		const id = btn.getAttribute('data-post-id');
		if (!id) return;
		const td = btn.closest('td');
		const original = btn.textContent;
		btn.textContent = '⏳';
		btn.disabled = true;

		try {
			const form = new FormData();
			form.append('action', 'bp_ai_alt_generate');
			form.append('nonce', NONCE);
			form.append('attachment_id', id);
			const res = await fetch(ajaxurl, { method: 'POST', body: form, credentials: 'same-origin' });
			const json = await res.json();
			if (!json.success) throw new Error(json.data && json.data.message ? json.data.message : 'Generation failed');

			// Update the visible alt text — replace the inline-edit span text.
			const span = td.querySelector('.bp-inline-edit');
			if (span) {
				span.textContent = json.data.alt;
			} else {
				td.insertBefore(document.createTextNode(json.data.alt + ' '), btn);
			}
		} catch (e) {
			alert('AI alt text failed: ' + e.message);
		} finally {
			btn.textContent = original;
			btn.disabled = false;
		}
	}

	document.addEventListener('click', function(e){
		const btn = e.target.closest('.bp-ai-alt-btn');
		if (!btn) return;
		e.preventDefault();
		e.stopPropagation();
		generate(btn);
	});

	// Initial scan, plus re-scan on DOM mutations (inline edits replace nodes).
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', scan);
	} else {
		scan();
	}
	new MutationObserver(scan).observe(document.body, { childList:true, subtree:true });
})();
JS;
	wp_add_inline_script('bp-inline-edit', $js);
});

/*--------------------------------------------------------------
# Sync alt text into existing <img> tags across the site
----------------------------------------------------------------
WordPress freezes an image's alt text into post content at insert
time and never updates it again, so the Media Library alt and the
on-page alt drift apart. This keeps them in sync: whenever an
attachment's _wp_attachment_image_alt changes — manually (media
modal, edit screen, the inline-edit column), via the sparkle icon,
or the cron generator — push the new value into every
<img class="wp-image-{ID}"> already baked into content.

Lives here (not in functions-media-replace.php) because that file
is admin-only, but alt can change during cron (bp_ai_alt_generate_cron),
and this file is loaded unconditionally.

Default: force-overwrite every matching tag. Filter
bp_alt_sync_force_overwrite to false to only fill empty/missing alts.
--------------------------------------------------------------*/

add_action('updated_post_meta', 'bp_alt_sync_meta_changed', 10, 4);
add_action('added_post_meta',   'bp_alt_sync_meta_changed', 10, 4);
function bp_alt_sync_meta_changed($meta_id, $object_id, $meta_key, $meta_value) {
	// update_post_meta only fires this when the value actually changed, so no
	// extra "did it change?" guard is needed.
	if ($meta_key !== '_wp_attachment_image_alt') return;
	if (get_post_type($object_id) !== 'attachment') return;

	bp_alt_sync_to_content((int) $object_id, (string) $meta_value);
}

/**
 * Push an attachment's alt text into matching <img> tags in post content.
 *
 * @param int    $attachment_id
 * @param string $new_alt
 * @return int   Number of posts updated.
 */
function bp_alt_sync_to_content($attachment_id, $new_alt) {
	global $wpdb;

	$marker = 'wp-image-' . (int) $attachment_id;
	$force  = (bool) apply_filters('bp_alt_sync_force_overwrite', true, $attachment_id);
	$like   = '%' . $wpdb->esc_like($marker) . '%';
	$count  = 0;

	// Pass 1 — post_content: main editor body + Elements CPT (site-header, widgets, etc.).
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts}
		 WHERE post_status IN ('publish','future','draft','pending','private')
		 AND post_content LIKE %s",
		$like
	), ARRAY_A);
	foreach ((array) $rows as $row) {
		if (is_serialized($row['post_content'])) continue; // never edit serialized blobs
		$new = bp_alt_update_img_alts($row['post_content'], $attachment_id, $new_alt, $force);
		if ($new !== $row['post_content']) {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %d", $new, $row['ID']));
			clean_post_cache($row['ID']);
			$count++;
		}
	}

	// Pass 2 — postmeta: the Page Top / Page Bottom sections and any other meta-stored
	//    HTML live here, NOT in post_content. Plain-string meta only; serialized
	//    values like arrays or page-builder blobs are skipped to avoid corruption.
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT pm.meta_id, pm.post_id, pm.meta_value
		 FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_status IN ('publish','future','draft','pending','private')
		 AND pm.meta_value LIKE %s",
		$like
	), ARRAY_A);
	foreach ((array) $rows as $row) {
		if (is_serialized($row['meta_value'])) continue;
		$new = bp_alt_update_img_alts($row['meta_value'], $attachment_id, $new_alt, $force);
		if ($new !== $row['meta_value']) {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d", $new, $row['meta_id']));
			clean_post_cache($row['post_id']);
			$count++;
		}
	}

	return $count;
}

/**
 * Rewrite the alt="" on every <img> in $html that carries the wp-image-{ID}
 * marker. Adds an alt attribute if one is missing. srcset/sizes left alone.
 *
 * @param bool $force  true = overwrite any alt; false = only fill empty/missing.
 */
function bp_alt_update_img_alts($html, $attachment_id, $new_alt, $force) {
	// Match the exact wp-image-{ID} token, not a prefix (so 12 doesn't match 123).
	$class_re = '/wp-image-' . (int) $attachment_id . '(?![0-9])/';
	if (!preg_match($class_re, $html)) return $html;

	$new_attr = 'alt="' . esc_attr($new_alt) . '"';

	return preg_replace_callback('/<img\b[^>]*>/i', function ($m) use ($class_re, $new_attr, $force) {
		$tag = $m[0];
		if (!preg_match($class_re, $tag)) return $tag; // not this attachment

		if (preg_match('/\balt\s*=\s*("|\')(.*?)\1/i', $tag, $a)) {
			if (!$force) {
				$current = html_entity_decode($a[2], ENT_QUOTES);
				if (trim($current) !== '') return $tag; // already has alt — leave it
			}
			return preg_replace('/\balt\s*=\s*("|\')(.*?)\1/i', $new_attr, $tag, 1);
		}

		// No alt attribute at all — insert one right after <img.
		return preg_replace('/<img\b/i', '<img ' . $new_attr, $tag, 1);
	}, $html);
}
