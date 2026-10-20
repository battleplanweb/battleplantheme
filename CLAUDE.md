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
| AI Chat widget + Twilio SMS handoff | `docs/ai-chat.md` |

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
- Page content has NO comment syntax → `docs/css.md`
- Every `:hover` rule must also style `.active` and `.tab-focus` (ADA) → `docs/css.md`
- Headings are variable-driven — style by `--h-*`, and use the tag for OUTLINE, not for size → `docs/css.md`
- Check what the framework already positions BEFORE adding grid/flex/position/line-height — header strips are already vertically centred (`.strip-elem`); never fake centring with `line-height` → `docs/css.md`
- Font weight is `normal` or `bold` — never `400`/`500`/`600`/`700` → `docs/css.md`
- Layout: default to `display: grid`, override to `flex` only with a reason → `docs/css.md`
- Conversion tracking — name it `conversion-*` and it reports itself → `docs/shortcodes-content.md`

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
- `includes-events.php` — Events calendar
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

## Site Pulse — Permissions & Impersonation (follow these or it leaks)

**God is NOT a feature flag — it is a role that holds every capability.** Every action on the site must be a grantable capability (to roles *and* individuals). `site_pulse_effective_caps()` grants a god the entire catalog, so a god passes every `site_pulse_user_can()` check automatically — feature code never needs to know what a god is. Therefore **never hard-gate a feature on `site_pulse_is_god()`**; gate on the capability. `site_pulse_is_god()` is reserved for exactly four things: **impersonation, Modules (always god-only), grant/revoke-god, and see-superadmin.** Anything else god-gated is a bug — it can't be delegated and it breaks the moment a non-god needs it (this is what took down Import Comment Cards for a non-god who'd been granted `import_data`). To add a new gated feature: add a cap to `site_pulse_capability_catalog_all()`, attach it to its module's `caps` in `site_pulse_modules()`, and gate with `site_pulse_user_can( site_pulse_effective_user_id(), 'cap' )` (optionally `|| site_pulse_god_can_override()`).

A god (Odin) can impersonate any user ("view as"). **The hard rule:** while impersonating, the god must see and do EXACTLY what that user can — nothing more. New code inherits this automatically *only* if it follows these patterns:

1. **Resolve the actor with `site_pulse_effective_user_id()`, never `get_current_user_id()`, for any permission/data decision.** The effective id is the impersonated user while impersonating (and the real user otherwise). Using `get_current_user_id()` for a cap check reads the *god's* full caps while impersonating → leak. `get_current_user_id()` is only for real-god controls (the Odin bar, the `site_pulse_impersonate` start/stop handler).

2. **Gate access with `site_pulse_user_can( site_pulse_effective_user_id(), 'cap' )`.** This already respects roles, per-user overrides, and module toggles.

3. **For any "…or the god can do it" shortcut, use `site_pulse_god_can_override()` — NEVER raw `site_pulse_is_god()` / `site_pulse_is_god( get_current_user_id() )`.** `site_pulse_god_can_override()` = real god AND not impersonating, so the bypass goes inert while viewing-as someone. (The helper itself and the god-bar body class use a `site_pulse_is_god( (int) get_current_user_id() )` form on purpose so a blanket find/replace of the literal skips them.)

4. **JS-side god UI:** gate on `D.userCaps` (effective) or `D.isGod` — both are already impersonation-aware (`sitePulseData.isGod` is set from `site_pulse_god_can_override()`). Never re-derive "is god" in JS from anything else.

Caps/roles, nav, panels, widgets, and the JS localization all read the effective user, so anything built on caps inherits impersonation for free. The only ways to break it are (1) checking the raw current user for caps, or (2) a raw `is_god()` bypass — don't do either. Module on/off still shows real state on Settings → Modules (don't route that through the override).

---
