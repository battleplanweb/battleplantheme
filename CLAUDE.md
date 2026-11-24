# Battle Plan Theme Framework — Claude Reference

## Overview
Custom WordPress framework theme built from scratch by Glendon Guttenfelder (Battle Plan Web Design).
Hosted on WP Engine. CDN via Cloudflare. Deployed via GitHub Actions (GitHub Desktop → tag → workflow pushes to each WP Engine install over SSH).
Text domain: `battleplan`. Version format: `YYYY.WW.revision` (e.g. `2026.38.10`).

Each client site uses this framework as a parent theme with a minimal child theme (`battleplantheme-site`) that overrides only what is necessary.

---

## Repository & Deployment
- GitHub: `https://github.com/battleplanweb/battleplantheme`
- Deployed by the `.github/workflows/deploy-*.yml` workflows (standard / sensitive / single site) — GitHub Updater is retired
- Child theme folder on desktop: `D:/{Client}/battleplantheme-site/`
- Base/template child theme: `D:/00 - Battle Plan Assets/theme/battleplantheme-site/`

---

## Reference Docs — read the matching file BEFORE doing the task

The detailed reference lives in `docs/` beside this file (from a client folder: `{business root}/00 - Battle Plan Assets/theme/GitHub/battleplantheme/docs/`). These are not auto-loaded — open the one that matches the task first.

| Task | Read first |
|---|---|
| Starting a site, editing `functions-site.php` (body classes, `customer_info`, optional features) | `docs/site-config.md` |
| Writing page markup / `page-content.txt` — `[section]` `[layout]` `[col]` `[group]` `[txt]` `[img]` `[vid]` `[expire]` `[restrict]` | `docs/shortcodes-layout.md` |
| Content shortcodes — `[get-biz]` `[get-years]` `[get-post-slider]` `[btn]` `[accordion]` `[widget]` brand/HVAC shortcodes, SEO titles/descriptions, conversion tracking | `docs/shortcodes-content.md` |
| Elements CPT, Page Top/Bottom meta boxes, site header/sidebar structure, image conventions | `docs/page-structure.md` |
| Any form — `[bp_form]` / `[seek]`, fields, patterns, recipients, uploads, emails, spam | `docs/forms.md` |
| PayPal / Pay in 4 | `docs/payments-paypal.md` |
| Media replace, DB search & replace, AI alt text | `docs/admin-tools.md` |
| Any CSS — `style-site.css` or framework `style-*.css`, plus the rendered-HTML selector map | `docs/css.md` |
| Jobsite GEO (CPT, taxonomies, scoring, API, app) | `docs/jobsite-geo.md` |
| Events + Booking (Booking Types = live-linked templates → Offerings / Reservations / Customers screens, sessions, events as an offering's website page — booking owns the dates; each date or all dates together — paid seats via Store checkout) | `docs/events-booking.md` |
| AI Chat widget + Twilio SMS handoff | `docs/ai-chat.md` |
| HVAC replacement price estimator `[hvac-estimator]` (per-site prices in the `hvac_estimator` option) | `docs/hvac-estimator.md` |
| Editing a LIVE site's page content, Page Top/Bottom, SEO title/description or image alt text (`wp bp` over SSH) | `docs/cli.md` |

When a framework function is removed or renamed, grep every client `script-site.js` / `functions-site.php` for callers before shipping.

### Hard rules that live in those docs (summary — the doc has the why)
- ⚠️ [nested] — DEPRECATED (2026-08-02). Never use it on a new build. → `docs/shortcodes-layout.md`
- Writing SEO titles & descriptions (audits) — use shortcodes, never hard-code → `docs/shortcodes-content.md`
- ⚠️ A full-bleed section cannot live in the WordPress editor → `docs/page-structure.md`
- NEVER wrap the logo in an `<a href="/">` — the framework already links it → `docs/page-structure.md`
- Archive images — the first one loads eagerly (don't "fix" this back) → `docs/page-structure.md`
- ⚠️ NEVER write a raw regex in `pattern="…"` — use a named pattern → `docs/forms.md`
- ⚠️ The browser never sets the price → `docs/payments-paypal.md`
- ⚠️ "Pay in 4 isn't showing" — it's almost never the code → `docs/payments-paypal.md`
- ⚠️ Pay in 4 vs Pay Monthly — PayPal picks, you can't → `docs/payments-paypal.md`
- FIRM RULE — reuse existing classes; never rebuild what's already styled → `docs/css.md`
- Units — `px` everywhere, `rem` only for headline font sizes. Never `em`. → `docs/css.md`
- style-site.css is COMPILED — it is never served directly → `docs/css.md`
- NEVER put animated OR TRANSFORMED text inside a `background-clip: text` element → `docs/css.md`
- Custom brand headline fonts — ALWAYS ship a metric-matched fallback → `docs/css.md`
- Buttons are variable-driven — set `--button-*`, don't override `.button` directly → `docs/css.md`
- Styled lists — use the framework `.bullet` system, don't hand-roll list CSS → `docs/css.md`
- `class="unique"` opts a control OUT of the button system — icon controls only, never a real button → `docs/css.md`
- `script-pages.js` rewrites every front-end page's DOM (input ids, empty `<p>` removal) — module markup must survive it → `docs/forms.md`
- Overriding a button's look? List `:hover`/`:focus`/`:active`/`.tab-focus` too — the framework's hover rule is (0,2,1) and reclaims it under the cursor → `docs/css.md`
- Short pages get a content floor from `#page` (flex column, 100dvh) so the footer isn't left mid-screen — the footer is NOT pinned, and `#page`'s children's margins don't collapse → `docs/css.md`
- Page content has NO comment syntax → `docs/css.md`
- Every `:hover` rule must also style `.active` and `.tab-focus` (ADA) → `docs/css.md`
- Headings are variable-driven — style by `--h-*`, and use the tag for OUTLINE, not for size → `docs/css.md`
- Check what the framework already positions BEFORE adding grid/flex/position/line-height — header strips are already vertically centred (`.strip-elem`); never fake centring with `line-height` → `docs/css.md`
- Font weight is `normal` or `bold` — never `400`/`500`/`600`/`700` → `docs/css.md`
- Layout: default to `display: grid`, override to `flex` only with a reason → `docs/css.md`
- Conversion tracking — name it `conversion-*` and it reports itself → `docs/shortcodes-content.md`
- Live-site edits go through `bp-wp <install> bp …` — `bp get` first, `--dry-run` and show the diff before every write, HTML via stdin only; every SEO pass also runs `bp images` and fixes weak alt → `docs/cli.md`

---

## CSS & Styling Rules
- Always apply the `theme-css-system` skill when creating or editing any CSS file or PHP template.
- Never invent section-specific classes (e.g., `.billing-card`, `.settings-input`) when an existing shared component class or `:root` CSS variable achieves the same layout or style.
- Never write arbitrary pixel values for spacing, sizing, or border-radius; always snap to existing `--space-*` or `--radius-*` variables (framework `:root` in `style.css`; fixed whole-pixel — never make them fluid) → `docs/css.md`
- **Class naming = `<base> <base>-<module>`** (Glendon, 2026-11-20), e.g. `sp-section sp-section-store`, `sp-card sp-card-review`. Every element carries the base component class, so it defaults to the base look. The module class goes on the component's root only, and exists only to override. Its children keep their base classes (`.sp-card-review .sp-card__title` when one needs a tweak). Generic variants keep `--` (`sp-card--flush`, `sp-options--inline`).
- **Condense into the base.** Put as much as possible in the base rule. A module rule exists only when there's a real reason (a layout that screen needs), never to re-state or nudge the base (a 14px pad where the base is 16). A new module should need no CSS at all until it has a reason. Choices are `.sp-options` + `.sp-check-row`, never a toolbar; toolbars are only bars of controls.

---

## Framework Directory Structure

```
battleplantheme/
├── functions.php                  # Entry point → loads functions-global.php
├── functions-global.php           # Constants, customer_info, bot detection, rand seed
├── functions-shortcodes.php       # All content shortcodes
├── functions-grid.php             # Layout shortcodes: section, layout, col, group, img, vid (+ deprecated nested)
├── functions-public.php           # Front-end hooks and output
├── functions-admin.php            # Admin customizations
├── functions-admin-columns.php    # Custom admin list columns
├── functions-admin-stats.php      # Admin stats display
├── functions-ajax.php             # AJAX handlers
├── functions-chron.php            # Cron orchestrator
├── functions-chron-analytics.php  # Cron: analytics tasks
├── functions-chron-gbp.php        # Cron: Google Business Profile sync
├── functions-chron-helpers.php    # Cron: shared helper functions
├── functions-chron-housekeeping.php # Cron: cleanup tasks
├── functions-cpt.php              # Custom post types (registered in framework)
├── functions-forms.php            # Form handling
├── functions-global.php           # Constants and globals
├── functions-grid.php             # Layout/grid shortcodes
├── functions-media-replace.php    # Media replace (absorbed Enable Media Replace plugin; admin-only)
├── functions-media-cleanup.php    # Clean Up Library (unused-image scan → quarantine → purge) + bp_media_in_use()
├── functions-icons.php            # Icon system
├── functions-ai-alt.php           # AI alt-text generation (Claude vision) + alt→content sync
├── functions-style-sheets.php     # CSS enqueueing logic
├── style.css                      # Theme declaration (WordPress + GitHub Updater — do not remove)
├── header.php / footer.php        # Site wrappers
├── page.php / single.php          # Page/post templates
├── archive.php                    # Archive template
├── template-parts/                # content.php, content-search.php, etc.
├── pages/                         # Pre-built universal page templates (HVAC, privacy policy, etc.)
├── elements/                      # PHP partials: product overviews, brand-specific content
├── common/                        # Shared partials
├── includes/                      # Optional feature modules (hvac, events, woocommerce, etc.)
├── js/                            # script-*.js and script-*.min.js (one file per feature)
├── style-*.css                    # One CSS file per component/feature
├── fonts/                         # Framework fonts
├── vendor/                        # Composer autoload
├── _bot/                          # Bot detection assets
└── _prewp/                        # Pre-WordPress bootstrap files
```

---

## Child Theme Structure (minimal — 5-6 files)

```
battleplantheme-site/
├── style.css          # Child theme declaration (Template: battleplantheme)
├── functions-site.php # ALL site config lives here
├── style-site.css     # Site-specific CSS overrides + CSS variables
├── script-site.js     # Site-specific JS
├── fonts/             # Site-specific fonts
└── images/            # Site-specific images
```

### Starting a New Site
1. Copy `D:/00 - Battle Plan Assets/theme/battleplantheme-site/` to the client folder
2. Edit `functions-site.php` — fill in `battleplan_updateSiteOptions()` with client data
3. Edit `style.css` — update theme name if needed
4. Set body classes for the desired layout
5. Customize `style-site.css` with brand colors/fonts via CSS variables

### `page-content.txt` goes in the client's `Stuff/` folder — never in `battleplantheme-site/`

The paste-in page markup you hand over (`page-content.txt`, and any other
deliverable-notes file like it) lives beside the client's other source material:

```
{Client}/
├── battleplantheme-site/   # ONLY the files that deploy to WP Engine
├── Graphics/               # logo, mockups, source art
└── Stuff/                  # interview.txt, page-content.txt, notes
```

`battleplantheme-site/` is a deploy target — everything in it ships to the server, so a
notes file there is dead weight in the theme. Write `page-content.txt` straight to
`{Client}/Stuff/` and reference it from the site CLAUDE.md as `../Stuff/page-content.txt`.

### Never delete commented-out code in the child-theme files

When editing `functions-site.php`, `style-site.css`, or `script-site.js`, you may freely change the active code — but **leave every commented-out block exactly as it is. Do not delete it.** Those comments are intentional: loader/animation options, feature toggles, alternate layouts, and reference snippets Glendon keeps on hand to flip on later. Likewise, if you stop using a piece of *active* code, **comment it out — don't remove it.** When in doubt, preserve; never silently strip a file down.

---

## Cron System

- **Trigger**: Non-SERP bot hits the site (not real users, preserving UX)
- **Window**: 10pm–5am local time with random spread (`bp_next_nightly_window()`)
- **Lock**: Transient `bp_chron_jobs_lock` (3 second lock prevents double-runs)
- **Override**: Logged in as `battleplanweb` bypasses the lock
- **Files**: `functions-chron.php` (orchestrator) → helpers, analytics, GBP, housekeeping
- **Skipped for**: sitemap requests, feed requests

---

## JavaScript Architecture

One JS file per feature (`script-carousel.js`, `script-magic-menu.js`, etc.), each with a `.min.js` version.
Site-specific JS goes in `script-site.js` in the child theme.
Always vanilla JS — never jQuery.

---

## JS Minification

**Node:** system Node at `C:\Program Files\nodejs\node.exe` — it **is** on the PATH, so just call `node`.

> Older versions of this file pointed at Adobe Dreamweaver 2021's bundled Node. **Dreamweaver 2021 was uninstalled — that path is dead.** Use the system Node.

**UglifyJS** 3.19.3 (self-contained, no npm needed) lives in the user's home folder:
`C:/Users/Glendon Guttenfelder/uglify-js/package/`

It was moved here from `AppData/Local/Temp/` because Windows clears that directory. If your Windows username differs, swap in `%USERPROFILE%/uglify-js/package/`. If it's missing entirely, re-download and extract:
```
https://registry.npmjs.org/uglify-js/-/uglify-js-3.19.3.tgz
```

**CLI form (what actually works from bash):**
```bash
node "C:/Users/Glendon Guttenfelder/uglify-js/package/bin/uglifyjs" in.js -c -m -o out.min.js
```

⚠️ **Note the `package/` segment in both paths.** The shorter `uglify-js/bin/uglifyjs` does **not** exist and fails with `MODULE_NOT_FOUND` — which silently leaves the **stale** `.min.js` on disk. It still passes `node --check`, so a syntax check won't catch it. After minifying, verify the new content actually landed by grepping the `.min.js` for a token you just added.

**Scripted form / minification options to use:**
```js
const UglifyJS = require('C:/Users/Glendon Guttenfelder/uglify-js/package/tools/node');
const fs = require('fs');
const code = fs.readFileSync('path/to/script.js', 'utf8');
const result = UglifyJS.minify({ 'script.js': code }, {
  mangle: true,
  compress: { drop_console: false, dead_code: true, unused: false },
  output: { comments: false }
});
if (result.error) { console.error(result.error); process.exit(1); }
fs.writeFileSync('path/to/script.min.js', result.code);
```

**To find files needing minification:** compare mtimes in `js/` — re-minify if the `.js` is newer than its `.min.js`, or no `.min.js` exists.

---

## Key Constants (set in functions-global.php)
| Constant | Value |
|---|---|
| `_BP_VERSION` | Current theme version |
| `_BP_NONCE` | Random base64 nonce (per-request) |
| `_HEADER_ID` | Post ID of the `site-header` Elements page |
| `_PLACES_API` | Google Places API key (from wp options) |
| `_JOBSITE_API` | Jobsite Geo API key |
| `_BREVO_API` | Brevo (email) API key |
| `_PAGE_SLUG` | Current page slug |
| `_PAGE_SLUG_FULL` | Full request URI |
| `_USER_LOGIN` | Current user login or 'anonymous' |
| `_USER_ID` | Current user ID or 0 |
| `_USER_ROLES` | Current user roles array |

---

## Business Name & HTML Entities (`functions-global.php`)
WordPress stores `blogname`/`blogdescription` **already esc_html'd** (`sanitize_option`), so
`get_bloginfo('name')` hands back `R &amp; S`, not `R & S`. Escape that again for an attribute,
or hand it to an AI prompt, and the page prints a literal `&amp;`.

**Decode as early as possible, escape as late as possible.**

| Helper | Use for |
|---|---|
| `bp_business_name()` | The company name as plain text — AI prompts, emails, `alt`/`content` attributes, JSON-LD |
| `bp_decode_text($v)` | Any plain-text value headed for `esc_attr`/`esc_html`/JSON (loops, so double-encoded values heal) |
| `bp_fix_entities($html)` | Content that must stay HTML — collapses only `&amp;amp;` → `&amp;`, leaves tags and correct entities alone |

- Never `esc_attr(get_bloginfo('name'))` — that is the double-escape. Use `esc_attr(bp_business_name())`.
- `<title>` is the exception: core echoes `wp_get_document_title()` unescaped, so the pre-encoded
  name is already correct there. Decode for og:/twitter:/JSON-LD consumers instead (SEO module does).
- Anything AI-generated gets `bp_fix_entities()` on save AND on render, so posts written before a
  fix still display right without a DB rewrite.

---

## Custom Post Types (built into framework)
- `testimonials`
- `galleries`
- `elements` — used for reusable page sections (header, footer, sidebar widgets, etc.)
- `landing` — landing pages (uses page.php template)
- `universal` — universal pages (uses page.php template)
- Additional CPTs can be registered in `functions-site.php` via `bp_registerMorePostTypes()`

## Loader Options
The base `functions-site.php` contains multiple commented-out loader animations to choose from:
- Spinning image/icon
- CSS spinner (default)
- Rotating circles
- 5 horizontal circles
- Vertical diamonds
- Swapping circles
- 5 spinning icons
- Fancy double-bounce
- Dancing squares
- And more — pick one, delete the rest

## Password strength nag is off, fleet-wide

WordPress never enforces password strength on the server — the "Very weak" readout, the
**Confirm use of weak password** checkbox, and the disabled submit button are all client-side
(`password-strength-meter.js` + `user-profile.js`). `battleplan_allow_weak_passwords()` in
[functions.php](functions.php) overrides `wp.passwordStrength.meter()` to always return 4
(Strong), so the checkbox never appears and the button never locks, and hides
`#pass-strength-result` so there's no badge either. Applies to `profile.php`, `user-new.php`,
and the `wp-login.php` reset-password screen.

Per-site opt-out (put it back the way core had it):

```php
add_filter( 'bp_allow_weak_passwords', '__return_false' );
```

Site Pulse's own password fields are custom markup and never used the core meter — they're
unaffected either way.

---

## Available `includes/` Modules (opt-in)
- `includes-hvac.php` — HVAC-specific functionality
- `includes-jobsite-geo.php` / `-api.php` — Jobsite location tracking
- `includes-events.php` — Events calendar (gate on `bp_events_on()` / `bp_calendar_on()`, never the option — see `docs/events-booking.md`)
- `includes-hvac-estimator.php` — `[hvac-estimator]` replacement price tool; `update_option( 'hvac_estimator', [ 'install' => 'true', … ] )` in functions-site.php (see `docs/hvac-estimator.md`)
- `includes-pedigree.php` — Dog breeding/pedigree
- `includes-timeline.php` — Timeline component
- `includes-woocommerce.php` — WooCommerce integration
- `include-hvac-products/` — HVAC product data by brand

## Pre-built `pages/` Templates (universal — load with [get-universal-page])
- `page-privacy-policy.php`
- `page-terms-conditions.php`
- `page-accessibility-policy.php`
- `page-review.php`
- `page-hvac-faq.php`
- `page-hvac-symptom-checker.php`
- `page-hvac-maintenance-tips.php`
- Various HVAC dealer certification pages (American Standard, Rheem, Ruud, Tempstar, York, Comfortmaker)

## Pre-built `elements/` Partials
HVAC product overview and "why choose" sections for all major brands:
American Standard, Amana, Bryant, Carrier, Comfortmaker, Goodman, Heil, Honeywell, Lennox, LG, Mitsubishi, Rheem, Ruud, Samsung, Tempstar, Trane, York

---

## WP Engine Gotchas
- EverCache caches pages including nonces — use `DONOTCACHEPAGE` + `nocache_headers()` on `template_redirect` for any page with user-specific content
- WAF (ModSecurity) strips POST fields named `password` on non-login endpoints — use an alternative field name (e.g. `member_pass`)
- `bp_enqueue_script` prefers `.min.js` — if a `.min.js` exists on the server, changes to the `.js` file are ignored until re-minified

---

## Site Pulse Store — one shop's customizations go in the SITE, never the framework
When a client wants their store to work differently (extra product fields, a section they don't need), add it in the child theme's `functions-site.php` through a filter. Don't edit the Store module for one shop.
- **`sp_store_product_setup`**: `hide` (`description` | `categories` | `tags` | `seo`) removes sections from the Site Pulse product editor. Hidden content is kept, not deleted. `fields` adds custom product fields: `label`, `type` (`text` | `textarea` | `number` | `date` | `select` with `options`), `placeholder`, `hint`, `page` (on the product page, default on), `card` (under the title on shop cards, default off). Values are stored in post meta `_sp_store_field_{key}`. Full doc block on `sp_store_product_setup()` in `includes-site-pulse-store-products.php`. On the page they render as `dl.store-single-fields`, and on cards as `p.store-card-field`; each item has a `store-field-{key}` class for site CSS. Example: Soil & Oil (Artist / Size / Date). `terms` (`[ 'tags' => 'Media' ]` or `'categories' => '…'`) moves that list from the bottom of the product page into the details list under that label. List products by a field with `[store-products field="artist" value="123"]`; only keys the site defines are accepted, and a blank value shows nothing.
- **Service fee** (2026-11-22, store DB 1.10): every cart, including paid bookings, gets one "Service fee" line. It is charged on subtotal less discount, before shipping and tax, and is never itself taxed. It is two stored shares:
  - `fee_card_cents` / `fee_card_rate`: setting `fee_card_rate`, client-editable in Settings → E-Commerce → Service fee.
  - `fee_platform_cents` / `fee_platform_rate`: setting `fee_platform_rate`. It is Battle Plan's share, invoiced by hand; its definition is only added for `site_pulse_is_god()`, so clients can't see or save it.
  - Both rates default to 0, so each store is set up by hand. They clamp to 0–10, and 0 turns a share off.
  - Math: `sp_store_fees_for()` in `includes-site-pulse-store-totals.php`, called by `sp_store_quote()`. Stripe gets the fee as a line item (tax code `txcd_00000000` under Stripe Tax); Clover gets it as a cart line.
  - **Memberships** pay it unless the `fee_memberships` toggle ("Charge it on memberships", default on) is off. Off leaves membership products out of the fee base entirely.
  - A renewing membership's share is stored on the order as `fee_renew_card_cents` / `fee_renew_platform_cents`. Stripe charges it as a recurring "Service fee" line, and the rest of the fee stays a one-off on the first invoice. Each `invoice.paid` renewal payment row gets those shares in `meta` (`sp_member_renewal_fee_meta()`).
  - Store toggles save off as `'0'`, never `''`, because `wpdb::get_var()` returns null for an empty string and the setting would fall back to its default. Read toggles with `=== '1'`.
  - Receipts use `{fee_line}`, which is auto-inserted before `{shipping_line}` in saved templates that predate it.
  - E-Commerce → **Revenue** (`sp_store_ajax_fees`, was Service Fees; 2026-11-22) is the whole money report for a range: what sold by kind (products / downloads / memberships incl. renewals / bookings, from line subtotals), discounts, both fee shares, shipping, tax, collected, refunds, net, best sellers and a by-month list. It counts paid, partly refunded and fully refunded orders by placed date (refunds subtract), plus renewal payments.
- **Stripe on-site checkout** (2026-11-23): Settings → E-Commerce → Payment processor → "Where customers pay" = `embedded` (default; Stripe Embedded Checkout mounted in `/checkout/`) or `hosted` (redirect). The publishable key lives on Settings → API Keys (`store_stripe_publishable` = the Store's `stripe_publishable` setting, or wp-config `BP_STORE_STRIPE_PUBLISHABLE`). `sp_store_stripe_embedded()` falls back to the redirect when the pk is missing or its mode disagrees with the secret key. Same Checkout Session + `ui_mode=embedded` + `return_url`; webhook, return page and key permissions unchanged. Stripe.js is injected by `script-store.js` (`BPStorePay.stripe`), allowed by the CSP's `strict-dynamic`.
- Other existing per-site store filters: `sp_store_catalog_header` (markup printed under the catalog heading, with or without results; Soil & Oil uses it for search + A–Z), `sp_store_empty_message` (the no-products text), `sp_store_image_categories` (image categories given to product photos, default `[ 'Products' ]`; Soil & Oil adds "Artwork"), `sp_store_price_variants` (which of a product's active variants set its card/list price and price sort, default all = a range; Soil & Oil keeps only the first, the original painting), `sp_store_base_slug`, `sp_store_picker_style`, `sp_store_sort_options`, `sp_store_per_page_choices`, `sp_store_catalog_is_home`, `sp_store_cart_lifetime`, `sp_store_checkout_slug`.
- **What a business calls its things** (2026-11-22): `site_pulse_term( 'product' | 'service', 'one' | 'many' )`, and `spTerm(key, many, lower)` in JS from `sitePulseData.terms`. Products are set in Settings → E-Commerce (`store_name_one` / `_many`, e.g. Artwork); services in Settings → Booking → What You Call Them (`booking_name_one` / `_many`, e.g. Retreats). Blank = Product(s) / Service(s). Use it for every new user-facing "product"/"service" word in Site Pulse. It is Site Pulse only; the public catalog title is still `post_type_archive_title` / `sp_store_base_slug`.
- If a customization a client asks for has no hook yet, add a **generic** filter to the framework, then use it from the site.

---

## Site Pulse Website Content (module `content`, off by default)
Add, edit and delete the site's pages and posts from Site Pulse (`includes/includes-site-pulse-content.php`). wp-admin keeps working, and WordPress revisions are kept as usual.
- **Content types:** posts and pages by default. A site adds others in `functions-site.php` with `add_filter( 'sp_content_types', fn( $t ) => $t + [ 'testimonials' => true, 'artists' => 'Artists' ] );`. `true` uses the post type's own label.
- **Permissions:** five levels per type, generated like the per-report caps: `content_{type}_write`, `_publish`, `_edit_all`, `_delete_own`, `_delete_all`. They appear in the Roles grid under "Website Content". When the module is switched on, owner, admin and system-admin are granted every level for every type, including types added later. A writer without `publish` can only Save Draft or Submit for Review. Reviewers (people with `edit_all` for that type) get a `content_review` notification, and the writer gets `content_published` when it goes live. Without `edit_all`, people see only their own items.
- **Layout pages:** only the inside of each `[txt]` block is editable; every other part of the markup is kept byte-for-byte. Shortcodes inside a text block become locked `<code class="sp-wc-chip" data-sc="N">` chips that carry only an index back to the original. They can be moved or deleted, but not edited or forged. Only changed blocks are rewritten. A save is refused if the page changed on the server since it was opened (fingerprint). Page Top / Page Bottom (`page-top_text` / `page-bottom_text`) are edited the same way when a page has them. Editor HTML must be filled **after** `markUniqueSpans()`, or that function's span rewrites get saved into the page.
- The home page and the blog page can't be deleted from here.

---

## Site Pulse lists of many items get the 1 / 2 / cards toggle
Every Site Pulse screen that lists many items (orders, products, categories, tags, customers, services…) offers the one-column / two-column / cards layout toggle (Glendon, 2026-11-22). It's automatic: add the list container's id to `SP_COL_LISTS` in `script-site-pulse.js`, and render the rows as `.sp-card.sp-card--flush > .sp-list > .sp-row` (the split and the cards CSS work on that shape). `initListColumns()` adds the toggle to the toolbar or tabs right above the list, defaults to two columns, and remembers each screen's choice on the device. Below 1280px it is always one column. A list with drag grips (`[data-sp-reorder]`) stays one column. Never use a `<table>` for a list of records; use rows with a search and sort select, as Users does. Exempt: two-pane inboxes (Messages, Customer Messages), where the list sits beside the open thread.

## Site Pulse rows that open an editor end in the round pencil, never an arrow
A row you click to edit something you set up (a product, service, template, event, page, plan) ends with `spEditMark()`, the round pencil, placed before its trash button (Glendon, 2026-11-22). The arrow (`spIcon('chev-r')`) is only for opening a record to view it (an order, a booking, a report, a mileage day) or picking from a list. A thing's whole setup lives in its one editor: no separate buttons on the row for parts of it, so a service's Dates are a section inside the service editor.

## Site Pulse inputs with a default show it as the placeholder
Any Site Pulse input that falls back to a default when left blank must show that default as its `placeholder` (Glendon, 2026-11-22), so people can see what blank means. Store settings do it automatically: `sp_store_setting_defs()` entries send `placeholder` (their own `placeholder` key, else `default`).

## Site Pulse ⓘ help (use it, don't add visible hint text)
Explanations live behind an ⓘ button that opens a short panel under the label (Glendon, 2026-11-21) — so hints can be real instructions without cluttering screens. New code builds the pair with `spInfoPair(text)` → `[buttonHTML, panelHTML]` (button inside the `<label>`/heading, panel right after it). Existing screens convert automatically in `markUniqueSpans()` → `spInfoize()`: a `.sp-form-group > .sp-help-text` and a `.sp-settings-card > h3 + .sp-help-text` become ⓘ. Opt out with `.sp-help-text--keep` (status/warnings that must be seen); hints with an `id` (JS-updated) and empty ones are skipped. Styles: `.sp-info`, `.sp-info__text` in style-site-pulse.css. The button is the same filled "?" disc as `.sp-help` (2026-11-22), so every hint looks alike.

---

## Site Pulse — Permissions & Impersonation (follow these or it leaks)

**God is NOT a feature flag — it is a role that holds every capability.** Every action on the site must be a grantable capability (to roles *and* individuals). `site_pulse_effective_caps()` grants a god the entire catalog, so a god passes every `site_pulse_user_can()` check automatically — feature code never needs to know what a god is. Therefore **never hard-gate a feature on `site_pulse_is_god()`**; gate on the capability. `site_pulse_is_god()` is reserved for exactly four things: **impersonation, Modules (always god-only), grant/revoke-god, and see-superadmin.** Anything else god-gated is a bug — it can't be delegated and it breaks the moment a non-god needs it (this is what took down Import Comment Cards for a non-god who'd been granted `import_data`). To add a new gated feature: add a cap to `site_pulse_capability_catalog_all()`, attach it to its module's `caps` in `site_pulse_modules()`, and gate with `site_pulse_user_can( site_pulse_effective_user_id(), 'cap' )` (optionally `|| site_pulse_god_can_override()`).

A god (Odin) can impersonate any user ("view as"). **The hard rule:** while impersonating, the god must see and do EXACTLY what that user can — nothing more. New code inherits this automatically *only* if it follows these patterns:

1. **Resolve the actor with `site_pulse_effective_user_id()`, never `get_current_user_id()`, for any permission/data decision.** The effective id is the impersonated user while impersonating (and the real user otherwise). Using `get_current_user_id()` for a cap check reads the *god's* full caps while impersonating → leak. `get_current_user_id()` is only for real-god controls (the Odin bar, the `site_pulse_impersonate` start/stop handler).

2. **Gate access with `site_pulse_user_can( site_pulse_effective_user_id(), 'cap' )`.** This already respects roles, per-user overrides, and module toggles.

3. **For any "…or the god can do it" shortcut, use `site_pulse_god_can_override()` — NEVER raw `site_pulse_is_god()` / `site_pulse_is_god( get_current_user_id() )`.** `site_pulse_god_can_override()` = real god AND not impersonating, so the bypass goes inert while viewing-as someone. (The helper itself and the god-bar body class use a `site_pulse_is_god( (int) get_current_user_id() )` form on purpose so a blanket find/replace of the literal skips them.)

4. **JS-side god UI:** gate on `D.userCaps` (effective) or `D.isGod` — both are already impersonation-aware (`sitePulseData.isGod` is set from `site_pulse_god_can_override()`). Never re-derive "is god" in JS from anything else.

Caps/roles, nav, panels, widgets, and the JS localization all read the effective user, so anything built on caps inherits impersonation for free. The only ways to break it are (1) checking the raw current user for caps, or (2) a raw `is_god()` bypass — don't do either. Module on/off still shows real state on Settings → Modules (don't route that through the override).

---
