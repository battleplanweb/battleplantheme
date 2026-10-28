# Battle Plan Theme — Forms

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

## Forms (`functions-forms.php`)

Custom form system that **replaced Contact Form 7 + Akismet**. Lives entirely in PHP, no admin UI. Submissions go to a REST endpoint at `/wp-json/bp/v1/contact`.

### CF7 legacy shim — old `[contact-form-7]` tags are auto-caught

WordPress leaves *unregistered* shortcodes as literal text, so any page still holding an old `[contact-form-7 …]` tag would print it raw. The framework registers a shim (`bp_cf7_shim` in [functions-forms.php](functions-forms.php), on `init` priority 99) that maps the orphaned tag to the right bp form by its CF7 `title` attribute: *quote/estimate/request* → `[bp-quote-form]`, *employ/application/career/job* → `[bp-employment-form]` (HVAC, only if registered), everything else → `[bp-contact-form]`. It only registers when `wpcf7_contact_form_tag_func()` is absent, so a site still running the real CF7 plugin keeps its own handler. The nightly housekeeping cron also runs `bp_cf7_sweep_content()`, which rewrites the leftover tag out of `post_content` (incl. Elements CPT) and plain-string `postmeta` (skips serialized blobs) so the content gets permanently cleaned — a near-no-op once a site is clean (LIKE-gated).

### The two standard forms

These are defined once in [functions-forms.php](functions-forms.php) and shared across all 130+ sites — change them in the framework, every site updates.

| Shortcode | Fields |
|---|---|
| `[bp-contact-form]` | Name, Email, Phone, Message |
| `[bp-quote-form]` | Name, Email, Phone, City, Message |

Both accept `redirect="/thanks/"` and `submit="Send My Message"` to override the post-submit redirect URL and submit-button label, plus `class="…"` which lands on the `.bp-form` wrapper (e.g. `class="form-label-outside"` to revert that one form to outside labels).

### Field shortcodes (for custom form bodies)

All field shortcodes go inside `[bp-form id="..."]…[/bp-form]`. Wrap each field in `[seek label="..." id="..." req="true|false" label-pos="top|before|after"]…[/seek]` to get the label + form-input grid wrapper.

| Shortcode | Required attrs | Optional attrs |
|---|---|---|
| `[bp-text]` | `name` | `placeholder`, `autocomplete`, `value`, `required`, `minlength`, `maxlength`, `pattern` (**named only — see below**), `class` |
| `[bp-email]` | (defaults `name="user-email"`) | same as above |
| `[bp-tel]` | (defaults `name="user-phone"`) | same as above |
| `[bp-textarea]` | (defaults `name="user-message"`) | `rows`, `cols`, `placeholder`, `value`, `required`, `maxlength` |
| `[bp-date]` | `name` | `min`, `max`, `placeholder`, `required` |
| `[bp-number]` | `name` | `min`, `max`, `step`, `placeholder`, `value`, `required` |
| `[bp-select]` | `name`, `options` | `first` (placeholder option label), `value` (preselected), `required` |
| `[bp-radio]` | `name`, `options` | `value` (preselected), `required` |
| `[bp-checkbox]` | `name` | `value` (default `1`), `checked`, `required`, `label` (or pass label between tags) |
| `[bp-checkboxes]` | `name`, `options` | `value` (comma-separated preselected) |
| `[bp-file]` | `name` | `accept` (e.g. `"jpg,png,pdf"`), `size` (max MB), `multiple`, `required` |
| `[bp-recipient-select]` | `name`, `options` (Label::email pairs) | `first` (placeholder), `required` — see below |
| `[bp-hidden]` | `name`, `value` | `param` — fill from that URL query arg (see below) |
| `[bp-submit]Label[/bp-submit]` | (label as content) | `class` |

### Field icons — built in, no markup (`style-forms.css` → "Field icons")

Every floating-label field gets a small icon at its left edge, picked from the `.bp-control-wrap`'s own `data-name` / `data-type`, so the standard forms, the modal and custom `[bp-form]` bodies all get them for free:

| Icon | Matches |
|---|---|
| person | `data-name` `user-name`, `user-first-name`, `user-last-name` |
| pin | `user-city`, `user-address`, `user-street`, `user-zip` |
| envelope | `data-type="email"` |
| phone | `data-type="tel"` |
| chat | `data-type="textarea"`, or `data-name="user-message"` (the quote form's message is a one-line `[bp-text]`) |

Any other field gets no icon and keeps its normal padding. Outside-label modes (`form-label-outside`, `label-pos-after`, `width-none`) never get them.

- **Opt out:** `form-no-icons` on the `<body>`, the `.bp-form` wrapper (`[bp-quote-form class="form-no-icons"]`), or one `.form-input`.
- **Tint:** `--field-icon-color` / `--field-icon-color-focus`. They default to `--label-color` / `--label-color-focus`, so most sites need nothing.
- **New icon:** add a `.bp-control-wrap[data-name="…"] { --field-icon: url("data:image/svg+xml;utf8,…") }` rule **and** add the same selector to the `:is()` lists in the gating rules below it. A field that matches the gate but has no `--field-icon` paints a solid square.
- **The wrap is `position: relative`** (so the icon centres on the control, not on the label + error row), which puts it in a later paint layer than the absolutely-positioned label. That is why the icon rules give the label `z-index: 2`. Drop that and the input background covers the label.
- Padding is set as `--input-padding` **on the control**, not on the wrap. The floating-label rule above it sets that variable directly on `input`/`textarea`, so a value inherited from the wrap would lose.

### Which button sent them? — `[bp-hidden param="…"]`

To have the email say which of several buttons sent the visitor to the form, link each button with a query arg (`[btn link="/contact/?service=Speaking"]`, spaces as `+`) and put `[bp-hidden name="user-service" param="service"]` in the form. `script-forms.js` copies `?service=` into the field on load, **client-side** because the page is cached. With no arg in the URL the field stays empty, and the email drops empty fields, so a direct visit adds no line.

- **Put it at the TOP of the form.** The email lists fields in the order they were posted, so a field added at the end prints under the message. On a standard form, prepend it with `bp_form_html` (insert after the opening `<form …>` tag). A slot would wrap a hidden input in an empty `[layout]`/`[col]` that still takes up space.
- **Keep the values clear of the spam word lists.** `bp_spam_check()` runs `bp_blocklist_words()` and `bp_blocklist_kill()` against **every** field, not just the message, as case-insensitive substrings. So `?service=Marketing`, `?service=SEO+Audit` or `?service=New+Website` gets the whole submission blocked as spam.

### ⚠️ NEVER write a raw regex in `pattern="…"` — use a named pattern

**A shortcode attribute cannot hold a regex.** Two separate WordPress behaviours mangle it, and *both fail silently* — nothing errors, nothing logs, the field just stops working:

| What you write | What actually happens | Why |
|---|---|---|
| `pattern="\d{5}"` | becomes `pattern="d{5}"` → **rejects every valid entry** ("Please match the requested format" on correct input) | `shortcode_parse_atts()` runs `stripcslashes()` on every value, which drops the backslash of any escape it doesn't recognise — `\d`→`d`, `\w`→`w`, `\s`→`s`, `\.`→`.` |
| `pattern="[0-9]{5}"` | tag **truncates** at the `]`; the rest of the regex **prints on the page** as literal text | `get_shortcode_regex()` matches the attribute section as `[^\]\/]*` — the shortcode tag ends at the first `]` |

That rules out `\d`, `\w`, `\s`, `[0-9]`, `[A-Za-z]`, `[[:digit:]]` — i.e. **every normal way to write "a digit."** (Same `]` rule bites any attribute, not just `pattern` — never put a `]` in a shortcode attribute value.)

**So name the pattern; the framework resolves the real regex in PHP where those characters are safe:**

```
[bp-text name="user-zip" required="true" autocomplete="postal-code" maxlength="10" pattern="zip"]
```

Built-in names (`bp_field_patterns()` in [functions-forms.php](functions-forms.php)):

| Name | Matches |
|---|---|
| `zip` | `75001` or `75001-1234` |
| `zip5` | 5-digit ZIP only |
| `phone` | digits + the usual separators, 10–20 chars |
| `digits` | digits only |
| `letters` | letters + spaces |
| `name` | letters, spaces, `.` `'` `-` (2+ chars) |
| `state` | 2-letter state abbreviation |
| `year` | `19xx` / `20xx` |

Need one that isn't listed? **Add it to the map via the filter — never inline a regex in the shortcode:**

```php
add_filter('bp_field_patterns', function($p) {
    $p['po-number'] = 'PO-[0-9]{6}';
    return $p;
});
```

An unrecognised value passes through untouched, so a legacy literal regex still renders — but only by luck, if it happens to contain neither a backslash nor a `]`. Don't rely on it.

*(HTML5 `pattern` is implicitly anchored — `^(?:…)$` — so don't add `^` or `$` yourself.)*

**`options` attribute syntax:**
- Simple: `options="Yes|No|Maybe"` → value === label for each
- Value/label pairs: `options="yes::Yes I do|no::No I don't"` → uses `::` separator
- Always pipe-separated, double-colon for value/label split

**Field naming convention:** prefix with `user-` so the auto label map and existing CSS selectors recognize them. The label map (`bp_label_for_field()` in [functions-forms.php](functions-forms.php)) auto-handles: `user-name`, `user-email`, `user-phone`, `user-message`, `user-subject`, `user-city`, `user-state`, `user-zip`, `user-address`, `user-business`, `user-position`, `user-service`, `user-date`, `user-time`, `user-contact`, `user-comments`, `user-recipient`. Anything else falls back to title-casing the slug (`user-pet-name` → "Pet Name"). For non-`user-` field names, the label is title-cased directly.

**Custom labels per form** — use the `bp_field_labels` filter to override the auto map for a specific form (e.g. when "Location" should read "Tattoo Location" in the email):

```php
add_filter('bp_field_labels', function($labels, $form_id) {
    if ($form_id === 'tattoo-inquiry') {
        return array_merge($labels, [
            'user-recipient'  => 'Artist',
            'user-age'        => 'Over 18?',
            'user-location'   => 'Tattoo Location',
            'user-description'=> 'Tattoo Description',
        ]);
    }
    return $labels;
}, 10, 2);
```

### Recipient selector — `[bp-recipient-select]`

Lets the user pick **who** the form goes to (e.g. "Which artist would you like to contact?"). Routes the email to the picked recipient without exposing email addresses to spammers.

```
[bp-recipient-select name="user-recipient" first="No Preference"
                     options="Dawn::dawn@example.com | Sam::sam@example.com | Tori::tori@example.com"
                     required="true"]
```

- Each option is `Display Label::email@address.com` (pipe-separated)
- **Note the asymmetry vs `[bp-select]`:** `bp-select` uses `value::label` (HTML-standard, value first); `bp-recipient-select` uses `label::email` (label first, since the email is the hidden routing target). They are *not* interchangeable parsers
- The `<select>` shows labels and posts labels (so the email body shows "Artist: Dawn", not the email address)
- **Multi-recipient routing**: each option's email value can be a comma-separated list (e.g. `Complaints::info@x.com, comments@x.com`). All listed addresses receive the email. Each is sanitized independently; invalid ones are silently dropped, valid ones rejoined as a comma-separated `To` header
- The framework embeds an HMAC-signed map (using `wp_salt('auth')`) of label → email in hidden inputs; the REST handler verifies the signature, then resolves the posted label back to the real email
- Tampered or unknown values fall back to the form's default recipient AND mark the submission as spam
- Only one `[bp-recipient-select]` per form

### File uploads — `[bp-file]`

```
[bp-file name="artwork-attach-1"]                             ← uses default accept list
[bp-file name="artwork-attach-1" size="25"]                   ← raise per-file cap to 25MB
[bp-file name="resume" accept="pdf,doc,docx"]                 ← restrict to specific types
```

- `accept` is **optional**. Default: `jpg,jpeg,png,gif,webp,avif,heic,pdf,doc,docx,eps,tif,tiff` — covers 99% of cases including iPhone (`heic`) and modern web (`webp`/`avif`) image formats. Only set it when you want to restrict (e.g. `accept="pdf"` for a resume-only field) or extend (e.g. `accept="jpg,png,pdf,ai,psd"` if a site genuinely needs Adobe source files — see note below)
- Site-wide default override: `add_filter('bp_file_default_accept', fn() => 'jpg,png,pdf');`
- `size` declares the per-file size cap **in MB** (default 10MB). If multiple `[bp-file]` fields in a form declare different sizes, the largest wins as the form-wide cap
- The framework auto-derives the server-side allowlist + size cap from these attributes, HMAC-signs them (using `wp_salt('auth')`), and embeds them as hidden inputs — so **one shortcode declaration governs both the browser picker AND the server-side enforcement**, without per-site filter boilerplate
- Files are validated against `wp_check_filetype_and_ext` (real MIME sniff, not client-claimed Content-Type), saved to `wp-content/uploads/bp-form-tmp/`, attached to the email, and deleted after `wp_mail()` completes
- A hardcoded blocklist (`php`, `phtml`, `phar`, `pl`, `py`, `sh`, `cgi`, `exe`, `bat`, `cmd`, `msi`, `js`, `mjs`, `html`, `htm`, `svg`) is always rejected regardless of the form's declared list — defense in depth
- **Auto-conversion of modern image formats to JPG**: Brevo (and some other transactional email APIs) silently strip `.webp`, `.avif`, `.heic`, `.heif` attachments — they accept the API call but drop those files. The framework auto-converts these to `.jpg` (via `wp_get_image_editor`, which uses Imagick or GD) before passing to `wp_mail`. Customer uploads `photo.heic` from an iPhone, recipient gets `photo.jpg` of the same image. Override the conversion list per form: `add_filter('bp_form_convert_to_jpg', fn() => ['webp', 'avif', 'heic', 'heif', 'tiff'], 10, 2);`
- **`.ai` and `.psd` are intentionally not in the default allowlist.** Brevo strips these silently and conversion to a recipient-friendly format (PDF/PNG) loses layers/vectors that designers care about. If a site really needs to accept Adobe source files, add `accept="...,ai,psd"` on the specific `[bp-file]` field — but the cleaner UX is to ask the customer to export to PDF or PNG before uploading
- **Debug logging:** to trace a submission, define `BP_FORM_DEBUG` to `true` in `wp-config.php`, or append `?bp_form_debug=1` to the page URL when submitting. Logs go to `wp-content/debug-bp-form.log`. Off by default — no overhead in production

**Optional filter overrides** (rarely needed — `[bp-file]` attributes are the source of truth):

```php
// Per-form filetype override (e.g. block PDF on a specific form even though the default allows it)
add_filter('bp_form_allowed_filetypes', function($types, $form_id) {
    if ($form_id === 'public-feedback') return ['jpg', 'jpeg', 'png'];
    return $types;
}, 10, 2);

// Override the form-declared size cap (rarely needed)
add_filter('bp_form_max_attachment_mb', fn($mb, $form_id) => $form_id === 'huge-uploads' ? 100 : $mb, 10, 2);
```

### Standard field row patterns

**Name + Email + Phone in one row** — always use `grid="3-3-2"` (Name and Email get equal width, Phone gets a narrower column):

```
[layout grid="3-3-2"]
    [col][seek label="Name"  id="user-name"  req="true"][bp-text  name="user-name"  required="true" autocomplete="name"][/seek][/col]
    [col][seek label="Email" id="user-email" req="true"][bp-email name="user-email" required="true"][/seek][/col]
    [col][seek label="Phone" id="user-phone" req="true"][bp-tel   name="user-phone" required="true"][/seek][/col]
[/layout]
```

Use this exact pattern whenever a form opens with Name/Email/Phone — don't reach for `1-1-1` or stack them.

### Building a custom form

Four patterns, in order from simplest to most powerful:

**Pattern 1: Drop a field into a named slot** — `bp_form_slot` (preferred)

Both standard bodies carry a `[bp-slot]` marker after every field, so you can place a field **anywhere** in the shared framework form from `functions-site.php` — no `str_replace`, no redefining the body. The precise filter is `bp_form_slot_{form-id}_{slot}`, which needs no `if` and no extra arg count:

```php
add_filter('bp_form_slot_quote_after-phone', function($fields) {
    return '[seek label="Service Needed" id="user-service" req="true"]
        [bp-select name="user-service" first="— select —" options="AC Repair|Heating Repair|Maintenance" required="true"]
    [/seek]';
});
```

Or the general form, when one callback covers several slots/forms:

```php
add_filter('bp_form_slot', function($fields, $slot, $form_id) {
    if ($form_id === 'quote' && $slot === 'before-message') {
        $fields .= '[seek label="Preferred Date" id="user-date"][bp-date name="user-date"][/seek]';
    }
    return $fields;
}, 10, 3);
```

**Slots, in render order** (both standard forms, so the same slot name works on either):

| Slot | Position |
|---|---|
| `top` | very top of the form |
| `after-name` | after Name |
| `after-city` | after City (**quote form only** — the contact form has no City) |
| `after-email` | after Email |
| `after-phone` | after Phone |
| `before-message` | between the contact-info row and the Message row |
| `after-message` | after Message |
| `before-button` | immediately before the submit button |
| `bottom` | below the submit button (where `bp_form_extra_fields` lands) |

Notes:
- **Return bare field markup** (`[seek]…[/seek]`). The slot wraps it in the `[col]` — or `[layout][col]` for the row-level slots — that its surrounding grid needs. Return your own `[col]` / `[layout]` to take over the wrapping instead.
- **Slot-added fields are first-class.** They resolve during the `[bp-form]` body pass, so `required="true"` registers into the signed `bp_required` payload and `[bp-file]` accept/size declarations register too — server-side enforcement comes along for free.
- **Multiple filters stack** on one slot (append with `.=` in the general form), and the precise `bp_form_slot_{form-id}_{slot}` filter runs after the general one, so it can see and override it.
- **Grid reflow on the contact form.** Its top row is `grid="3-3-2"` (3 columns), so a field added at `after-name` takes Email's cell and pushes Phone onto a second line. That's correct positioning, just a reflowed row — if you want the new field on its own full-width line, use `before-message`, or return `[col class="span-all"]…[/col]`. The quote form's fields are a single-column stack (`grid-1`), so every slot there is exactly positional.
- **`[bp-slot]` works in custom bodies too** — drop `[bp-slot name="whatever"]` in your own `[bp-form]` and that form becomes extensible the same way.

**Pattern 2: Append below the button** — `bp_form_extra_fields` filter

```php
add_filter('bp_form_extra_fields', function($fields, $form_id) {
    if ($form_id === 'quote') $fields .= '
        [seek label="Service Needed" id="user-service" label-pos="top"]
            [bp-select name="user-service" first="— select —" options="AC Repair|Heating Repair|Maintenance" required="true"]
        [/seek]
    ';
    return $fields;
}, 10, 2);
```

The oldest hook, kept for the sites already using it. It appends after the **entire** body — and since the standard bodies include their own `[bp-submit]`, the added fields render **below the submit button**. Identical to the `bottom` slot. For anything that should sit above the button, use Pattern 1.

**Pattern 3: Replace an entire form body** — register a new shortcode

```php
add_shortcode('site-application-form', function() {
    return do_shortcode('[bp-form id="application"]
        [layout grid="1-1"]
            [col][seek label="Name" id="user-name" req="true" label-pos="top"][bp-text name="user-name" required="true" autocomplete="name"][/seek][/col]
            [col][seek label="Email" id="user-email" req="true" label-pos="top"][bp-email name="user-email" required="true"][/seek][/col]
        [/layout]
        [seek label="Phone" id="user-phone" req="true" label-pos="top"][bp-tel name="user-phone" required="true"][/seek]
        [seek label="Address" id="user-address" req="true" label-pos="top"][bp-text name="user-address" required="true" autocomplete="street-address"][/seek]
        [seek label="How old are you?" id="user-age" req="true" label-pos="top"]
            [bp-select name="user-age" first="— select —" options="0-10|11-25|26-50" required="true"]
        [/seek]
        [seek label="Do you speak English?" id="user-english" req="true" label-pos="top"]
            [bp-radio name="user-english" options="Yes|No" required="true"]
        [/seek]
        [seek label="Message" id="user-message" label-pos="top"][bp-textarea name="user-message" rows="5"][/seek]
        [seek label="button"][bp-submit]Submit Application[/bp-submit][/seek]
    [/bp-form]');
});
```

Then on the page that needs it: `[site-application-form]`. The form `id="application"` is what shows up in `bp_form_extra_fields($fields, $form_id)` filters and as the form_id in `bp_form_before_send` / `bp_form_after_send` hooks.

**Pattern 4: Rewrite a standard form's body wholesale** — `bp_form_body` filter

```php
add_filter('bp_form_body', function($body, $form_id, $type) {
    if ($type !== 'quote') return $body;
    return '…entirely different shortcode body…';
}, 10, 3);
```

`$type` is `'contact'` or `'quote'` (which default body was requested); `$form_id` is the `id` attr, so a `[bp-quote-form id="service-call"]` is distinguishable. Use this only to **remove or reorder** built-in fields — for *adding* fields, Pattern 1 does the job without you owning a copy of the framework body that then goes stale when the framework's changes.

### Email rendering — automatic, no template

There is **no per-form email body template** (intentional — the old CF7 system required editing a template per form per site). The REST handler walks the posted fields and auto-generates the email body. Configuration lives in PHP:

| Email piece | Default | How to override |
|---|---|---|
| **Recipient** | `customer_info['email']` | `recipient="email@..."` attr on `[bp-form]`, or `bp_form_before_send` filter |
| **Subject** | `'Customer Contact · {user-name}'` for any form, or `'Quote Request · {user-name}'` if `id="quote"` | `subject="..."` attr on `[bp-form]`, or filter |
| **Body** | Auto label/value table from posted fields + sender metadata footer | Filter `bp_form_before_send` to mutate `$email['body']` |
| **From** | `Website · {customer_info[name]} <email@admin.{domain}>` | Filter |
| **Reply-To** | Auto-extracted: `{user-name} <{user-email}>` | Filter |
| **Cc** | (none) | Filter — set `$email['cc']` in `bp_form_before_send` |
| **Bcc** | `Website Administrator <email@bp-webdev.com>` | Filter |

For per-form email customization use the filter:

```php
add_filter('bp_form_before_send', function($email, $ctx) {
    if ($ctx['form_id'] === 'application') {
        $email['subject'] = 'Job Application: ' . ($ctx['fields']['user-name'] ?? 'Unknown');
        $email['to'] = 'hr@example.com';
    }
    return $email;
}, 10, 2);
```

The `$ctx` passed to the filter contains: `form_id`, `fields` (array of all posted values keyed by field name), `customer` (full customer_info), `recipient`, `subject`, `referrer`, `ip`, `ua`, `spam` (string reason if blocked, empty if clean), `attachments`.

There is also a post-send action `bp_form_after_send($email, $ctx, $sent)` for things like central forwarding (used by `functions-rovin.php` for complaint forms).

### Custom email body template — `bp_form_email_template`

For visual parity with the old CF7 "Mail" body field — when you want field groups separated by blank lines, specific field ordering, or labels on their own line above their values — register a per-form template:

```php
add_filter('bp_form_email_template', function($template, $form_id, $ctx) {
    if ($form_id === 'tattoo-inquiry') {
        return "Artist: [user-recipient]

Name: [user-name]
Phone: [user-phone]
Email: [user-email]
Over 18? [user-age]

Tattoo Location:
[user-location]

Tattoo Description:
[user-description]

Message:
[user-message]";
    }
    return $template;
}, 10, 3);
```

Template syntax (mirrors the old CF7 mail body):
- `Label: [field-name]` → two-column row: bold label on left, value on right
- `Just a label like Tattoo Location:` (no `[token]` on the line) → label-only line, full-width bold
- `[field-name]` alone on a line → value-only line, full-width
- Blank line → vertical spacing
- `[_raw_user-foo]` is supported as a CF7-compat alias for `[user-foo]`
- `[_format_user-foo "D, M j, Y"]` is a CF7-compat format token. The field value is run through `strtotime() + date($format)` so a stored ISO date like `2026-05-17` renders as `Sun, May 17, 2026`. Use this for date fields so the line stays a `Label: [token]` row instead of pre-formatting in PHP (which would bake the date in as literal text and turn the row full-width-bold)
- Empty fields are dropped entirely (no dangling label)
- **Orphaned label-only lines are auto-dropped**: if a bare label line (`Additional Info:`) is immediately followed by a value-only token line (`[user-message]`) that resolves to empty, *both* lines are dropped together — so the email never shows a header with nothing underneath it

When no template is registered for a form, the framework auto-generates a single label-value table from posted fields in submit order. Most forms don't need a template — only reach for it when the email body needs specific layout/ordering.

### Post-submit redirect

After a successful submission the browser navigates to a thank-you page. Defaults and overrides:

- **Default:** `/email-received/` — every site should have this universal page (use `[get-universal-page slug="email-received"]` if the framework ships one, or build a custom one). Set globally in [functions-forms.php](functions-forms.php) as the `[bp-form]` shortcode's `redirect` default
- **Per-form override:** `[bp-form id="..." redirect="/thank-you/"]` — sets the redirect for that form everywhere it renders
- **Per-page override:** drop a `[bp-hidden name="bp_redirect" value="/survey-received/"]` inside the form body on a specific page. Hidden inputs added in the body appear *after* the framework's auto-emitted `bp_redirect` field, so the later value wins on submit. Useful when the same form on different pages should redirect to different thank-you pages
- **Disable redirect** (just show success message inline): `[bp-form id="..." redirect=""]`

### Standard employment form — `[bp-employment-form]`

Available on every site type (lives in `functions-forms.php`). Drop `[bp-employment-form]` on a page and it renders a full employment application: Name/Email/Phone, Years of Experience + checkbox grid of types (HVAC, Plumbing, Electrical, Other), three Yes/No screening questions (criminal history, driver's license, manual labor), positions of interest checkboxes, and an open message field. Submit goes to `customer_info['email']` by default with subject `Employment Application · {user-name}`, prefixed with `< QUALIFIED >` or `< unqualified >` based on the screening answers.

**Per-site customization** — every piece is filter-overridable:

```php
// Send to multiple recipients (default is customer_info['email'])
add_filter('bp_employment_recipients', function($emails) {
    $emails[] = 'invoices@example.com';
    return $emails;
});

// Send applicant a PDF auto-reply when qualified (no PDF = no auto-reply)
add_filter('bp_employment_autoreply_pdf', fn() => 'wp-content/uploads/pdf/Employment_App.pdf');

// Customize the auto-reply email body
add_filter('bp_employment_autoreply', function($autoreply, $ctx) {
    $autoreply['body'] = '<p>Custom thank-you copy here</p>';
    return $autoreply;
}, 10, 2);

// Replace screening questions (e.g. drop manual-labor, add weekend availability)
add_filter('bp_employment_screening_questions', function($q) {
    unset($q['manual-labor']);
    $q['weekends-ok'] = ['label' => 'Available weekends?', 'options' => 'Yes|No'];
    return $q;
});

// Customize position options ('' drops the question from the form and email)
add_filter('bp_employment_position_options', fn() => 'Service Tech|Install Tech|Office');

// Customize experience type options
add_filter('bp_employment_type_options', fn() => 'HVAC|Refrigeration');

// Custom qualification logic if screening questions differ
add_filter('bp_employment_qualified', function($qualified, $ctx) {
    $f = $ctx['fields'];
    return ($f['criminal-history'] ?? '') === 'No' && ($f['weekends-ok'] ?? '') === 'Yes';
}, 10, 2);
```

**Field labels and email body template** are auto-generated from the screening questions, so removing `manual-labor` automatically removes its row from both the form AND the email. No manual sync needed.

**To install on a site that needs it:**
1. Drop `[bp-employment-form]` on a page
2. (Optional) Add `bp_employment_recipients` if multiple recipients
3. (Optional) Add `bp_employment_autoreply_pdf` if you want the PDF auto-reply
4. (Optional) Customize screening/positions/types as needed

For sites that just want the standard form, all you need is the shortcode on the page — zero `functions-site.php` changes.

### Test-mode reroute (built-in)

When testing a form on a live client site, prefix the message with the word "test" and the email reroutes to `glendon@bp-webdev.com` instead of the client. Detection: the first word of the `user-message` field begins with "test" (case-insensitive) — matches `Test`, `Testing`, `test the form`, `tester`, etc.

- Subject gets prefixed with `<- TEST ->`
- BCC to website-admin is dropped for test emails
- Spam-flagged messages still go to the spam mailbox (spam intercept wins over test reroute)
- Override the test recipient: `add_filter('bp_form_test_recipient', fn() => 'someone@else.com');`
- A real customer message starting with "I am testing my heater" goes to the client normally — only the **first word** triggers the reroute

### Spam protection (built-in, no config)

Every submission runs through this pipeline automatically:

1. **Honeypot** — hidden `bp_hp` field; non-empty = bot
2. **Speed check** — `<5 seconds` from form render to submit = bot
3. **HMAC verification** — cache-friendly form-token (no WordPress nonce, EverCache-safe)
4. **Required-field enforcement** — every `[bp-…]` field marked `required="true"` is registered into a signed `bp_required` payload at render. The REST handler verifies the signature and confirms each named field has a non-empty value. Missing required fields = `Bot:incomplete` (silently spam-routed). Real users never hit this because their browser's HTML5 validation already enforced it; only direct POSTs that bypass the browser trip the check

If none of the above tripped, the content blocklist pipeline (`bp_spam_check()`) runs, in this order:

5. **Kill words** — the very first content check (`bp_blocklist_kill()`); ~55 high-confidence spam phrases. Matched against all fields (`stripos`), instant block (`reason: Kill`)
6. **Country block** — non-US senders blocked unless site-name matches whitelist
7. **Email blocklist** — ~150 known spam-sender email domains (`bp_blocklist_emails()`)
8. **Phone blocklist** — leading-digit checks
9. **Word blocklist** — ~250 known spam phrases ("audit your website", "boost your leads", etc.) (`bp_blocklist_words()`)
10. **Name blocklist + inline message checks** — known spam usernames, URLs in the message body, name-repeated-as-message, sequential phone digits, email/email-confirm mismatch

> The kill and word lists are kept de-duplicated: because matching is `stripos` (substring), a longer phrase that already contains a shorter listed phrase is redundant and is dropped — keep the shortest distinct phrase. (There is no AI spam filter — it was removed in favour of the deterministic blocklists.)

Spam submissions still receive a "thanks" response (so bots don't learn what tripped them) but the email gets rerouted to `email@bp-webdev.com` with a `<- SPAM: Blocked {reason} ->` subject prefix, and the IP is fire-and-forget logged to the central `bp-webdev.com/wp-content/email-add-ip.php` endpoint.

### Multi-step forms

Wrap a form body in `[bp-form-steps]…[/bp-form-steps]` and structure the body with `<div class="bp-step">` and `<div class="bp-step-included">` markers, plus `[bp-next]` / `[bp-prev]` buttons. The progress bar wires up automatically.

### The site's primary form (the "default" contact form)

Several places pick a form **without being told which one**:

| Where | Falls back to |
|---|---|
| Mobile menu bar email button + the modal it opens | `[bp-contact-form]` |
| 404 page | `[bp-contact-form]` |
| `[col type="form"]` widget column | `[bp-quote-form]` |

Quote-driven sites (HVAC etc.) opt the modal + button over to the quote form:

```php
add_filter('bp_primary_form', fn() => 'quote');    // or 'contact' (default), or 'none' to hide
```

**When the site has its own form, declare it the primary one** — otherwise the mobile email icon keeps opening the generic framework form while the contact page shows the custom one. Register the form with `bp_register_form()` instead of `add_shortcode()` and pass `'primary' => true`:

```php
bp_register_form('babes-contact-form', function() {
    return do_shortcode('[bp-form id="babes-contact"] … [/bp-form]');
}, [
    'primary' => true,               // this form IS the site's contact form
    'title'   => 'Send A Message',   // modal heading (optional)
    'type'    => 'contact',          // 'contact' (default) | 'quote' — mobile-bar button variant
]);
```

Already registered with plain `add_shortcode()`? One line next to it does the same:

```php
bp_set_primary_form('[babes-contact-form]', ['title' => 'Send A Message', 'type' => 'contact']);
```

Both live in [functions-global.php](functions-global.php) (not `functions-forms.php`) because `functions-site.php` is required **before** `functions-forms.php` — declaring at load time from the child theme would otherwise hit an undefined function.

Once declared, that form is what all three rows in the table above render, and `bp_primary_form_config()` is the single resolver everything calls.

Precedence, lowest to highest: framework default → `bp_set_primary_form()` registration → the filters. So the old escape hatches still win and existing sites are untouched:

```php
add_filter('bp_primary_form_title',     fn() => 'Book An Appointment');
add_filter('bp_primary_form_shortcode', fn() => '[paradise-tattoo-form]');
```

The filters only reach the mobile-bar modal — the 404 page and the widget form column follow the *registration*, so prefer `bp_set_primary_form()` when the custom form should stand in everywhere.

`bp_primary_form` still controls the mobile bar button's class (`mm-bar-quote` vs `mm-bar-contact`) and whether it shows at all (`'none'` hides button + modal). With a primary form registered it only selects that variant — the registered shortcode stays the form.

### When to add to functions-site.php vs the framework

- **Framework** (`functions-forms.php`): Anything every site needs. Currently: the two standard forms, the spam pipeline, the REST endpoint, the email builder.
- **Site** (`functions-site.php`): Site-specific forms (employment apps, complaint forms, custom intake forms), recipient overrides, label customizations, primary form CTA preference.
