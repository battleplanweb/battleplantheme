# Battle Plan Theme Framework — Claude Reference

## Overview
Custom WordPress framework theme built from scratch by Glendon Guttenfelder (Battle Plan Web Design).
Hosted on WP Engine. CDN via Cloudflare. Deployed via GitHub + WP Engine SSH using GitHub Updater.
Text domain: `battleplan`. Version format: `YYYY.WW.revision` (e.g. `2026.38.10`).

Each client site uses this framework as a parent theme with a minimal child theme (`battleplantheme-site`) that overrides only what is necessary.

---

## Repository & Deployment
- GitHub: `https://github.com/battleplanweb/battleplantheme`
- Deployed to each WP Engine site via SSH push
- GitHub Updater handles theme updates across all sites
- Child theme folder on desktop: `D:/{Client}/battleplantheme-site/`
- Base/template child theme: `D:/00 - Battle Plan Assets/theme/battleplantheme-site/`

---

## Framework Directory Structure

```
battleplantheme/
├── functions.php                  # Entry point → loads functions-global.php
├── functions-global.php           # Constants, customer_info, bot detection, rand seed
├── functions-shortcodes.php       # All content shortcodes
├── functions-grid.php             # Layout shortcodes: section, layout, col, nested, group, img, vid
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

---

## functions-site.php — The Brain of Each Site

### Body Classes
Set via `add_filter('body_class', ...)`. Pick one from each group:

| Group | Options |
|---|---|
| Header width | `header-edge` `header-full` `header-stretch` (default) |
| Menu style | `menu-clip` `split-menu` `menu-edge` `menu-full` `menu-stretch` (default) |
| Mobile menu | `side-slide` `side-push` `top-slide` `top-push` `top-drop` + optional `mobile-left` |
| Content width | `content-edge` `content-full` `content-stretch` (default) + optional `mobile-content-edge` |
| Footer width | `footer-edge` `footer-full` `footer-stretch` (default) |
| Info bar width | `info-edge` `info-full` `info-stretch` (default) |
| Sidebar | `sidebar-none` `sidebar-left` `sidebar-right` + optional `sidebar-edge` `sidebar-shift` |
| Sidebar style | `content-sidebar-box` `sidebar-line` `widget-box` (default) |
| Content box | `content-box` (default: none) |
| Accordion | `accordion-box` (default: none) |
| Forms | `form-stacked` (default: inline) |
| Post thumbnail | `thumb-left` `thumb-right` + optional `switch-thumb` |

### battleplan_updateSiteOptions() — All Fields

```php
$customer_info = array(
    "year"          => "202x",         // founding year (used by [get-years])
    "area-before"   => "",             // e.g. "(" for (469)
    "area-after"    => "-",            // separator after area code
    "area"          => "000",          // area code
    "phone"         => "000-0000",     // local number
    "name"          => "Business Name",
    "street"        => "123 Main St",
    "city"          => "City",
    "state-abbr"    => "TX",
    "state-full"    => "Texas",
    "zip"           => "75001",
    "default-loc"   => "City, TX",     // optional default location label
    "google-ad-loc" => "DFW",          // optional Google Ads location label
    "service-areas" => array(array('City', 'State'), ...),
    "service-type"  => array('HVAC solutions', 'expert installations...'),
    "service-names" => array(),
    "license"       => "TACLA00000C",
    "email"         => "email@domain.com",
    "facebook"      => "https://www.facebook.com/...",
    "instagram"     => "https://www.instagram.com/...",
    "finance-link"  => "https://...",
    "cid"           => "...",          // Google CID (no array)
    "pid"           => "...",          // Google Place ID (can be array for multi-location)
    "pid-sync"      => "false",
    "business-type" => "hvac",
    "site-type"     => "hvac",         // hvac | pedigree | carte-du-jour
    "site-brand"    => array('American Standard'),
    "google-tags"   => array(
        'prop-id'   => '000000000',    // GA4 property ID
        'analytics' => 'G-XXXXXXXX',
        'clarity'   => 'xxxxxxxxxx',   // optional: Microsoft Clarity project ID
        'ads'       => 'AW-XXXXXXXX',
        'event'     => array('phone_conversion_number', 'XXXXXXXXXXXXXXXX/XX-'),
    ),
    "scripts"       => array('magic-menu'), // optional extra scripts
    "lcp"           => 'site-background.webp',   // desktop LCP image filename
    "m-lcp"         => 'site-background-phone.webp', // mobile LCP image filename
    "cancel-holiday"=> "true",         // optional: disable holiday mode
);
```

### Optional Features (uncomment to activate)
```php
update_option('site_login', $loginArgs);      // Member login/portal system
update_option('timeline', array('install'=>'true'));
update_option('event_calendar', array('install'=>'true', 'abbr_days'=>'false'));
update_option('cdj_locations', array(...));   // Carte du Jour multi-location
update_option('jobsite_geo', array('install'=>'true', ...)); // Jobsite geo tracking
```
Delete the option to disable it. Default base template has these commented out.

---

## Cron System

- **Trigger**: Non-SERP bot hits the site (not real users, preserving UX)
- **Window**: 10pm–5am local time with random spread (`bp_next_nightly_window()`)
- **Lock**: Transient `bp_chron_jobs_lock` (3 second lock prevents double-runs)
- **Override**: Logged in as `battleplanweb` bypasses the lock
- **Files**: `functions-chron.php` (orchestrator) → helpers, analytics, GBP, housekeeping
- **Skipped for**: sitemap requests, feed requests

---

## Layout Shortcodes (`functions-grid.php`)

### [section] — Full-width page section
```
[section name="" class="" style="" width="" background="" left="50" top="50" css="" hash="" grid="" break="" valign="" start="" end="" track=""]
```
- `name` → sets `id` attribute (spaces/underscores become hyphens)
- `style` → adds `style-{value}` class
- `width` → adds `section-{value}` class
- `background` → sets CSS background-image with `left`/`top` position
- `grid` → if set, wraps content in `<div class="flex grid-{value}">` inside the section
- `start`/`end` → date-based show/hide (YYYY-MM-DD)

### [layout] — Flex grid container
```
[layout name="" grid="1-1" break="" valign="" class=""]
```
- `grid` → column layout. Two notations:
  - **Dash notation** (1–4 cols only): fractional ratios joined with dashes. Each number is a share of the total; col gets `share / total`.
    - `grid="1-1"` → 2 cols at 50% / 50%
    - `grid="1-1-1"` → 3 cols at 33% × 3
    - `grid="1-1-1-1"` → 4 cols at 25% × 4
    - `grid="2-1"` → 66% / 33%
    - `grid="1-2"` → 33% / 66%
    - `grid="1-2-1"` → 25% / 50% / 25%
    - `grid="2-1-1"` → 50% / 25% / 25%
    - `grid="2-3"` → 40% / 60%
  - **Equal notation** (5 or 6 cols only): `Ne` where N is the column count.
    - `grid="5e"` → 5 equal cols at 20%
    - `grid="6e"` → 6 equal cols at ~16.6%
  - **Never use more than 6 columns.** Don't write `grid="1-1-1-1-1"` — use `grid="5e"`. Don't write `grid="7e"` or `grid="8e"`.
- `valign` → vertical alignment — values: `left`, `center`, `right` (never `middle`)
- `gap` → **do not set this attribute.** Glendon adjusts column spacing via CSS in the rare cases it's needed. Same applies to `[col]`.
- `break` → **do not set this attribute.** Glendon adds responsive breakpoints manually if/when needed. Same applies to `[col]`.

### [nested] — Nested flex grid (inside a layout)
```
[nested name="" grid="1" break="" valign="" class=""]
```

### [col] — Column inside a layout
```
[col name="" class="" order="" break="" align="" valign="" h-span="" v-span="" background="" css="" hash="" start="" end="" track=""]
```
- `h-span`/`v-span` → CSS grid span values
- `order` → CSS order override
- `align` / `valign` → values: `left`, `center`, `right` (never `middle`)
- `gap` → **do not set.** Glendon tweaks via CSS only when needed.

**Critical rule — wrap multi-child content in `[txt]`:**
When a `[col]` contains more than one child element (text + icon, headline + paragraph, multiple inline pieces, etc.), wrap the contents in `[txt]…[/txt]`. Without it the children get separated by line breaks. The only time `[txt]` is **not** required is when the `[col]` contains a single element (e.g. just an `<img>`, just a `[btn]`, or just a `[get-menu]`).

```
[col align="center"]
 [txt]
  [get-icon type="checkmark" top="2"] Locally Owned &amp; Operated in Durant, OK <span class="divider">✦</span> Serving the Texoma Area Since 1995
 [/txt]
[/col]
```

The same rule applies to `[nested]` when it wraps multiple inline children. (`[group]` is practically deprecated — see below.)

### [group] / [txt] — Block wrapper (div.block.block-group/text)
```
[group size="100" class="" order="" start="" end="" track=""]
```

**Don't use `[group]` — write a raw `<div>` instead.** `[group]` is practically deprecated. Anywhere you'd reach for it (button rows, icon wrappers, contact rows, etc.), just write the `<div>` directly:

```
[txt]
 <h1>We Keep You Cool</h1>
 <p>…</p>
 <div class="hero-btns">
  [btn link="/contact/"]Schedule Service[/btn]
  <div class="phone-number">[get-biz info="phone-link" icon="phone"]</div>
 </div>
[/txt]
```

`[txt]` (the multi-child wrapper inside `[col]`) is **not** deprecated — keep using it.

**Prefer flat HTML — don't over-wrap.** When a small card or row only contains an icon, a label, and a sub-label, write them inline inside a single `<p>` rather than wrapping each in its own `<div>`. CSS can lay it out (float, flex on the parent, etc.) without the extra DOM:

```
[col class="stat-card"][txt]
 <p>[get-icon type="location-pin"]<strong>Locally Owned</strong><br>Since 1995</p>
[/txt][/col]
```

(Note the compact formatting — `[col …][txt]` and `[/txt][/col]` on the same line is fine for short blocks.)

### [img] — Image block wrapper
```
[img size="100" class="" order="" link="" get-biz="" new-tab="" ada-hidden="false" start="" end="" track=""]
```
- `get-biz` → uses `[get-biz info="{value}"]` as the link href

### [vid] — Video block (YouTube, Vimeo, or local)
```
[vid size="100" mobile="100" link="" thumb="" preload="false" related="false" controls="true" autoplay="false" loop="false" muted="false" begin="" start="" end="" track=""]
```

### [expire] — Date-gated content
```
[expire start="2025-01-01" end="2025-12-31"]content[/expire]
```

### [restrict] — Role-gated content
```
[restrict max="administrator" min="none"]content[/restrict]
```

---

## Content Shortcodes (`functions-shortcodes.php`)

### [get-biz] — Pull business info from customer_info
```
[get-biz info="name"]
[get-biz info="phone"]          → clickable tracked phone link
[get-biz info="phone-notrack"]  → plain phone link
[get-biz info="area"]
[get-biz info="email"]
[get-biz info="city"]
[get-biz info="state-abbr"]
[get-biz info="zip"]
[get-biz info="street"]
[get-biz info="facebook"]
[get-biz info="license"]
[get-biz info="mm-bar-phone"]   → mobile menu bar phone button
```
Optional: `icon=""` `left="0"` `top="0"`

### [get-years] — Auto-calculating years in business
```
[get-years start="2010"]             → "14 years"
[get-years start="2010" label="no"]  → "14" (number only)
[get-years start="2010" mult="2"]    → multiplier (e.g. combined years)
```

### [get-season] — Season-conditional content
```
[get-season spring="..." summer="..." fall="..." winter="..."]
```
Spring/fall fall back to summer/winter if not set.

### [get-post-slider] — Carousel/slider of posts, images, testimonials
```
[get-post-slider type="testimonials" num="4" auto="yes" interval="6000" loop="true"
  pics="yes" controls="yes" controls_pos="below" indicators="no"
  orderby="rand" order="asc" size="thumbnail"
  slide_type="box" slide_effect="fade" speed="fast"
  show_excerpt="true" show_content="false" show_date="false" show_author="false"
  pic_size="1/3" text_size="" title_pos=""
  tax="" terms="" tag="" exclude="" start="" end=""
  mult="1" class="" all_btn="View All" post_btn=""
  lazy="true" blur="false" mask="false"]
```
- `type` → post type slug (testimonials, post, galleries, etc.)
- `mult` → slides visible at once (1–5)
- `content_type` → `image` or `text`

### [side-by-side] — Images side by side, height-equalized via flex ratio math
```
[side-by-side img="123,456,789" size="half-s" gap="" align="center" break="none" class="" full="" pos="bottom"]
```
- `img` → comma-separated WordPress attachment IDs
- `size` → image size slug
- `full` → attachment ID that gets `full-{pos}` class
- Uses aspect ratio math so all images share equal height

### [get-icon] — SVG icon
```
[get-icon type="phone" class="" top="0" left="0" link="" sr="" new-tab="" before="" after="" grid=""]
```
- `before` / `after` → text rendered inside the icon span, before/after the SVG
- `link` → wraps the icon in `<a class="icon-btn">` (pair with `sr` for screen-reader text and `new-tab` to open in a new tab)
- `grid` → if set with content, splits into a 2-col layout: icon on the left, content (auto-wrapped in `[txt]`) on the right. Empty content falls back to icon-only output.

**Use `grid` for icon + stacked-text card patterns** (stat cards, feature pills, contact rows — anywhere the design is "icon on the left, two lines of text on the right"). Wrap a row of these in `<div class="icon-cards">` — no inner `<p>` or per-card class needed, just bare `[get-icon]` calls:

```
<div class="icon-cards">
 [get-icon type="location-pin" grid="1-2"]<strong>Locally Owned</strong><br>Since 1995[/get-icon]
 [get-icon type="shield" grid="1-2"]<strong>Licensed &amp; Insured</strong><br>For Your Safety[/get-icon]
 [get-icon type="money-bag" grid="1-2"]<strong>Financing Available</strong><br>Easy &amp; Affordable[/get-icon]
</div>
```

This is preferred over manually writing `[layout grid="1-2"][col]<icon>[/col][col][txt]…[/txt][/col][/layout]` — `[get-icon grid="…"]` does the same thing in one shortcode, and the `<div class="icon-cards">` wrapper is what CSS targets to style the row + each card.

### [get-universal-page] — Embed a universal page template
```
[get-universal-page slug="privacy-policy"]
```

### [get-nonce] — Output the nonce for inline scripts
```
[get-nonce]  → nonce="abc123..."
```

### [get-menu] — Output a navigation menu
```
[get-menu]
```

### [add-search-btn] — Add search button to menu or other areas
```
[add-search-btn]
```

---

---

## WordPress Page Structure

Every page is built from four areas, top to bottom:

```
┌─────────────────────────────┐
│  Site Header (Element CPT)  │  ← "site-header" element post
├─────────────────────────────┤
│  Page Top (meta box)        │  ← hero / splash section(s)
├─────────────────────────────┤
│  Main Content (WP editor)   │  ← body copy, images, shortcodes
├─────────────────────────────┤
│  Page Bottom (meta box)     │  ← bands of content: testimonials, teasers, coupons, etc.
├─────────────────────────────┤
│  Site Footer                │
└─────────────────────────────┘
```

On pages with a sidebar, the sidebar content is controlled by an **Elements CPT** post called `widgets`.

### Elements CPT
Reusable page sections managed as WordPress posts under the "Elements" post type.
Key elements used on every site:
- `site-header` — the masthead (logo strip, top strip, phone number, brand logo)
- `widgets` — sidebar widget stack (only on pages using a sidebar layout)

### Page Top Meta Box
Placed above main content. Typically used for:
- Hero/splash sections with background images
- Announcement bars
- Introduction sections with a background

### Page Bottom Meta Box
Placed below main content. Typically used for:
- Testimonial sliders
- Logo sliders (brand/partner logos)
- Teaser columns (services, about, products)
- Coupon sections
- "Why Choose Us" sections
- Emergency service graphics
- Google review widgets

### Typical Site Header Structure
```
[section class="top-strip"]
 [layout]
  [col align="center"]tag line or announcement[/col]
 [/layout]
[/section]

[section class="logo-strip"]
 [layout grid="1-1"]
  [col class="logo"]
   <div class="logo-holder">
    <img src="/wp-content/uploads/logo-1-WxH.png" alt="..." class="logo-top wp-image-{id}" width="W" height="H" />
    <img src="/wp-content/uploads/logo-2-WxH.png" alt="..." class="logo-mid wp-image-{id}" width="W" height="H" />
    <img src="/wp-content/uploads/logo-3-WxH.png" alt="..." class="logo-bot wp-image-{id}" width="W" height="H" />
   </div>
  [/col]
  [col align="center"]
   <div class="brand-logo">[get-brand-logo]</div>
   <div class="phone-number">[get-biz info="area-phone" icon="phone" top="8"]</div>
  [/col]
 [/layout]
[/section]
```
Note: `logo-top`, `logo-mid`, `logo-bot` are three logo variants that display at different scroll positions.

### Typical Sidebar (Widgets Element)
```
[widget type="form" title="Request A Quote"][/widget]
[widget type="symptom-checker"][/widget]
[widget type="financing"]...[/widget]
[widget type="customer-care"][/widget]
[widget type="image" priority="3"][get-emergency-service graphic='2'][/widget]
[widget type="credit-cards"][/widget]
[widget type="basic" priority="5"]custom HTML[/widget]
```

---

## Image Conventions

- Always use `.webp` format
- Always include `width` and `height` attributes
- Always include `style="aspect-ratio:W/H"` inline
- Always include `class="wp-image-{id}"` (WordPress standard)
- Use `alignright`, `alignleft`, or `aligncenter` class for floated images in body copy
- Image size slugs: `thumbnail`, `half-s`, `full-s`, `third-s`, `quarter-s`, etc.

```html
<img src="/wp-content/uploads/filename-WxH.webp"
     alt="Descriptive alt text with local city/keyword."
     class="alignright size-half-s wp-image-{id}"
     width="480" height="539"
     style="aspect-ratio:480/539" />
```

### Mockup placeholder

When building mockup/draft markup before the real images are picked, point **every** `<img src="…">` (the logo excepted) at one of these placeholders, chosen by the image's aspect ratio:

```
https://bp-webdev.com/wp-content/uploads/x-square.webp   ← square images (1:1)
https://bp-webdev.com/wp-content/uploads/x-horz.webp     ← landscape / wider-than-tall
https://bp-webdev.com/wp-content/uploads/x-vert.webp     ← portrait / taller-than-wide
```

Pick the one that matches the `width`/`height` (and `aspect-ratio`) you set on the tag: square → `x-square`, landscape → `x-horz`, portrait → `x-vert`. The **logo is the only exception** — use its real filename, never a placeholder.

Glendon swaps to the real image filenames once the design is locked. Don't invent fake filenames like `hero-tech-800x600.webp` — use the matching placeholder URL until told otherwise.

---

## Additional Shortcodes

### [btn] — Button
```
[btn link="/page/" align="center" size="100" class="" fancy="false" icon="false"
     new-tab="false" get-biz="" ada="" start="" end="" track="" onclick=""]Button Text[/btn]
```
- `fancy` → adds fancy button style (value = style variant, e.g. `"1"`)
- `icon` → icon slug (e.g. `"chevron-right"`) appended to button text
- `before` → `"true"` puts icon before text
- `graphic` → image filename from uploads, shown as button icon
- `get-biz` → pulls link from customer_info field
- `ada` → adds screen-reader-only text for accessibility

**Always prefer `[btn]` over raw `<a>` for buttons.** If the markup is `<a href="/contact-us/">Contact Us</a>`, write it as `[btn link="/contact-us/"]Contact Us[/btn]` — picks up framework button styles automatically.

**Phone numbers as buttons:** use `[get-biz info="phone-link" icon="phone"]` (already a tracked clickable link), wrapped in `<div class="phone-number">…</div>` so it can be styled in CSS:

```
<div class="phone-number">[get-biz info="phone-link" icon="phone"]</div>
```

Don't hand-build `<a href="tel:…">[get-icon type="phone"] [get-biz info="area-phone"]</a>` — that bypasses the framework's call tracking.

### [accordion] — Accordion block
```
[accordion title="Section Title" excerpt="" class="" active="false" btn="false"
           btn_collapse="false" icon="true" scroll="true" multiple="true"
           start="" end="" track=""]Content[/accordion]
```
- `active="true"` → open by default
- `multiple="false"` → collapse others when one opens
- Body class `accordion-box` styles the accordion container

### [widget] — Sidebar widget
```
[widget type="basic" title="hide" priority="2" lock="none" set="none"
         class="" show="" hide="" start="" end="" track=""]content[/widget]
```

**Widget types and their defaults:**
| Type | Auto title | Auto content | Priority | Lock | Notes |
|---|---|---|---|---|---|
| `form` | "Service Request" | `[bp-quote-form]` | 4 | top | Hidden on 404, contact, review pages |
| `basic` | — | custom | 2 | — | Generic widget |
| `image` | — | custom | 2 | — | Widget with image styling |
| `brand-logo` | — | `[get-brand-logo]` | 4 | top | |
| `financing` | — | custom | 3 | — | |
| `customer-care` | — | auto from site-brand | — | — | Hidden on customer-care page |
| `symptom-checker` | — | `[get-symptom-checker]` | 1 | — | Hidden on symptom-checker page |
| `credit-cards` | — | `[get-credit-cards]` | 3 | bottom | |
| `event` | — | custom | 2 | — | |
| `topper` | — | custom | 5 | top | |
| `filler` | — | `&nbsp;` | 5 | — | Spacer |
| `menu` | — | desktop nav menu | 5 | — | Special: outputs `#desktop-navigation` |

- `priority` → controls order in sidebar (lower number = higher/earlier)
- `lock="top"` → always stays at top regardless of priority
- `lock="bottom"` → always stays at bottom
- `set` → groups widgets visually
- `show` / `hide` → comma-separated page slugs to show/hide this widget on

### [why-choose-us] — HVAC brand "Why Choose Us" section
```
[why-choose-us style="2" width="full" alt="Custom alt text." brand="" img=""]
```
- `brand` → defaults to `site-brand[0]` from customer_info
- `img` → `"grey"` or `"white"` for tinted logo variant, or custom filename from uploads
- Loads pre-built element from `elements/element-why-choose-{brand}.php`
- Available brands: american-standard, amana, bryant, carrier, comfortmaker, lennox, rheem, ruud, tempstar, trane, york

### [get-brand-logo] — Display the brand dealer logo
Outputs the authorized dealer logo based on `site-brand` in customer_info.
Used in the site header and as a widget.

### [get-logo-slider] — Auto-scrolling logo strip
```
[get-logo-slider tag="featured" size="full" max_w="33" num="-1" speed="slow"
                 space="15" pause="no" link="false" lazy="false"
                 order_by="rand" order="ASC" direction="normal"]
```
- `tag` → image-tags taxonomy term (tag images in WP Media Library)
- `speed` → `"slow"` `"medium"` `"fast"`
- `package="hvac"` → loads standard HVAC brand logos automatically
- `direction="reverse"` → scrolls right-to-left

### [get-emergency-service] — Emergency service graphic
```
[get-emergency-service graphic='2']
```
Used in sidebar widget: `[widget type="image" priority="3"][get-emergency-service graphic='2'][/widget]`

### [hvac-maintenance-tips] — Pre-built maintenance tips page section
```
[hvac-maintenance-tips type=""]
```

### [hvac-tip-of-the-month] — Rotating HVAC tip
```
[hvac-tip-of-the-month]
```

### Additional [get-biz] info values
Beyond the basics, these are commonly used:
- `area-phone` → formatted phone number with area code as tracked link (+ optional icon)
- `phone-link` → phone as a tracked clickable link (used inline in body copy)
- `phone-notrack` → plain phone link, no tracking class
- `mm-bar-phone` → mobile menu bar phone button format

---

## Forms (`functions-forms.php`)

Custom form system that **replaced Contact Form 7 + Akismet**. Lives entirely in PHP, no admin UI. Submissions go to a REST endpoint at `/wp-json/bp/v1/contact`.

### The two standard forms

These are defined once in [functions-forms.php](functions-forms.php) and shared across all 130+ sites — change them in the framework, every site updates.

| Shortcode | Fields |
|---|---|
| `[bp-contact-form]` | Name, Email, Phone, Message |
| `[bp-quote-form]` | Name, Email, Phone, City, Message |

Both accept `redirect="/thanks/"` and `submit="Send My Message"` to override the post-submit redirect URL and submit-button label.

### Field shortcodes (for custom form bodies)

All field shortcodes go inside `[bp-form id="..."]…[/bp-form]`. Wrap each field in `[seek label="..." id="..." req="true|false" label-pos="top|before|after"]…[/seek]` to get the label + form-input grid wrapper.

| Shortcode | Required attrs | Optional attrs |
|---|---|---|
| `[bp-text]` | `name` | `placeholder`, `autocomplete`, `value`, `required`, `minlength`, `maxlength`, `pattern`, `class` |
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
| `[bp-hidden]` | `name`, `value` | — |
| `[bp-submit]Label[/bp-submit]` | (label as content) | `class` |

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

Two patterns, in order from simplest to most powerful:

**Pattern 1: Add fields to a standard form** — `bp_form_extra_fields` filter

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

The added fields are appended after the standard form body, before the submit button. Use this when the standard form is mostly right and you just need an extra question or two.

**Pattern 2: Replace an entire form body** — register a new shortcode

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

### Standard HVAC employment form — `[bp-employment-form]`

For HVAC sites only (loaded via `includes/includes-hvac.php`). Drop `[bp-employment-form]` on a page and it renders a full employment application: Name/Email/Phone, Years of Experience + checkbox grid of types (HVAC, Plumbing, Electrical, Other), three Yes/No screening questions (criminal history, driver's license, manual labor), positions of interest checkboxes, and an open message field. Submit goes to `customer_info['email']` by default with subject `Employment Application · {user-name}`, prefixed with `< QUALIFIED >` or `< unqualified >` based on the screening answers.

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

// Customize position options
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
5. **Country block** — non-US senders blocked unless site-name matches whitelist
6. **Email blocklist** — ~150 known spam-sender email domains
7. **Word blocklist** — ~250 known spam phrases ("audit your website", "boost your leads", etc.)
8. **Phone blocklist** — leading-digit checks
9. **AI filter** (if `ANTHROPIC_API_KEY` constant defined) — Claude Haiku evaluates the submission

Spam submissions still receive a "thanks" response (so bots don't learn what tripped them) but the email gets rerouted to `email@bp-webdev.com` with a `<- SPAM: Blocked {reason} ->` subject prefix, and the IP is fire-and-forget logged to the central `bp-webdev.com/wp-content/email-add-ip.php` endpoint.

### Multi-step forms

Wrap a form body in `[bp-form-steps]…[/bp-form-steps]` and structure the body with `<div class="bp-step">` and `<div class="bp-step-included">` markers, plus `[bp-next]` / `[bp-prev]` buttons. The progress bar wires up automatically.

### Per-site primary form CTA

The mobile menu bar contact button and the "request quote" modal default to the **contact form** — universal across business types. HVAC and other quote-driven sites should opt in to the quote form:

```php
add_filter('bp_primary_form', fn() => 'quote');    // or 'contact' (default), or 'none' to hide
```

To use a **custom form** (e.g. a site-specific shortcode like `[paradise-tattoo-form]`) override the modal title and shortcode independently — these win over whatever `bp_primary_form` resolved to:

```php
add_filter('bp_primary_form_title',     fn() => 'Book An Appointment');
add_filter('bp_primary_form_shortcode', fn() => '[paradise-tattoo-form]');
```

The `bp_primary_form` value still controls the mobile menu bar button's class (`mm-bar-quote` vs `mm-bar-contact`) and whether the button shows at all (`'none'` hides it) — so set it too if you need a specific visual variant.

### When to add to functions-site.php vs the framework

- **Framework** (`functions-forms.php`): Anything every site needs. Currently: the two standard forms, the spam pipeline, the REST endpoint, the email builder.
- **Site** (`functions-site.php`): Site-specific forms (employment apps, complaint forms, custom intake forms), recipient overrides, label customizations, primary form CTA preference.

---

## Media Replace (`functions-media-replace.php`)

Condensed, front-loaded replacement for the **Enable Media Replace** plugin — same idea as the forms system replacing CF7. Pure procedural PHP, admin-only (loaded from `functions.php` behind `is_admin()`), no admin settings page, no page-builder modules, no background-removal/upsell. The plugin's whole "remove background / ShortPixel upsell / notices / filesystem abstraction" stack was dropped; only the one workflow we use survives.

### What it does
Click an image in the Media Library → **Replace media** → upload a new file. The system:
1. Deletes **every** size of the old file (thumbnails, `-scaled`, original, backups).
2. Puts the new file in place, **keeping the uploaded file's own name** (made unique in the same folder if it differs from the original).
3. Regenerates all thumbnail sizes for the new file.
4. **Rewrites every link to the file across the whole database** to the new filename — but only when the name actually changed.

### Entry points (all surface the same screen)
- **Media Library list view** → row action "Replace media"
- **Attachment details modal** (grid view / block inserter) → "Replace media" field
- **Full edit-attachment screen** → "Replace Media" meta box (sidebar)

All require `current_user_can('edit_post', $attachment_id)`. The screen itself is a hidden submenu page under Media (`upload.php?page=bp-media-replace`), nonce-protected on both open and submit.

### Behavior decisions (baked in — no settings)
- **Alt text & Caption are always preserved** (the SEO-critical fields). On a **rename**, the attachment **Title and permalink slug are updated to the new filename** (matching the old EMR plugin), along with the file, `guid`, modified-date, and on-page links. A same-name replace changes nothing but the file bytes + regenerated sizes.
- **Always operates on the original**, never the `-scaled` derivative (avoids `image-scaled-scaled.jpg` recursion).
- **Same-name replacement is a safe no-op for links**: re-uploading a file with the identical name regenerates sizes but writes nothing to the DB (search/replace pairs collapse to empty). If the new image generates *different* sizes than the old, the differing size URLs are remapped to the nearest new size.

### The link rewriter (the hard part, ported from EMR's `Replacer`)
`bp_mr_replace_urls()` builds relative-URL maps for the old + new files (main file, `original_image`, and every thumbnail size), pairs them index-aligned (filling any missing new size with its nearest match by width), then runs a **serialize/JSON-aware** search & replace via `bp_mr_replace_content()` across:
- `posts.post_content` (publish/future/draft/pending/private — incl. the **Elements CPT** that holds headers/widgets), strict mode (objects not unserialized)
- `postmeta` (content posts only), `commentmeta`, `termmeta`, `usermeta`, `options`

It recurses through nested arrays/objects, renames URL-valued array keys, and re-serializes/re-encodes on the way out. Serialized values that fail an incomplete-class check are returned untouched rather than corrupted. Candidate rows are found with an extension-stripped `LIKE` key (escaped via `esc_like`).

### Auto-corrects `<img>` dimensions (beyond what the plugin did)
When the new image has different pixel dimensions than the old one, `bp_mr_fix_img_dimensions()` rewrites the `width`, `height`, and inline `style="aspect-ratio:W/H"` on every `<img>` in `post_content` whose `src` now points at one of the new file's sizes — using the freshly-regenerated metadata. This prevents the old "new picture gets stretched into the old picture's dimensions" distortion.
- Pulls the real per-size dimensions from `bp_mr_dimension_map()` (main image + each thumbnail size, keyed by relative URL path).
- **Runs on same-name replacements too**, not just renames: if you re-upload `photo.webp` at a new size, the URLs don't change but the dimensions do, so a content pass still runs (gated by `bp_mr_dimensions_changed()`).
- Only edits attributes that already exist (the framework always emits `width`/`height`/`aspect-ratio`). `srcset`/`sizes` are deliberately left alone — WordPress recomputes those live from the attachment metadata at render time.
- Runs on **plain-HTML `post_content` AND `postmeta`** — the Page Top / Page Bottom hero/band sections live in postmeta, so their `<img>` dimensions get corrected too (this is why a same-name resize of a hero updates its size). Never inside serialized/JSON blobs (rewriting a string there would break its byte-length prefix), and never on `commentmeta`/`termmeta`/`usermeta`/`options` — those get URL replacement only.

### Hook
`do_action('bp_media_replaced', $attach_id, $old_url, $new_url)` fires after a successful replace.

### Caveat
Only **database** references are rewritten (where ~all content lives, including Elements). Image URLs hardcoded in child-theme files (`style-site.css` background images, `functions-site.php`) are filesystem, not DB, and are **not** touched.

---

## Alt Text — AI generation + content sync (`functions-ai-alt.php`)

Loaded **unconditionally** (front-end + cron), because alt can be generated in the background.

### AI generation
`bp_ai_generate_alt_text($attachment_id)` sends the image to Claude vision (default `claude-haiku-4-5`) with business + parent-post context and returns SEO-aware alt text (80–125 chars). It does **not** save — the caller decides. Requires `ANTHROPIC_API_KEY` or `BP_ANTHROPIC_API_KEY`; no-ops (and hides the UI) if neither is set.
- **Sparkle icon (✨)** in the Media Library "Alt Text" column → AJAX → generate → `update_post_meta(_wp_attachment_image_alt)`.
- **Cron** `bp_ai_alt_generate_cron` → same, in the background (used by jobsite_geo auto-generation).
- Overrides: `BP_AI_ALT_MODEL`, `BP_AI_ALT_MAX_TOKENS`.

### Alt → content sync
WordPress bakes alt into post content at insert time and never updates it, so library alt and on-page alt drift. This hooks `updated_post_meta`/`added_post_meta` on `_wp_attachment_image_alt` (so it fires for manual edits, the sparkle icon, AND the cron generator) and pushes the new alt into every `<img class="wp-image-{ID}">` already in content.
- **Force-overwrites** every matching tag by default (adds an `alt=""` if one is missing). Filter `bp_alt_sync_force_overwrite` → `false` to only fill empty/missing alts instead.
- Matches on the exact `wp-image-{ID}` token (framework standard; not a prefix, so 12 ≠ 123). Hand-written tags lacking that class aren't touched.
- Scans **`post_content`** (main editor + Elements CPT) **and `postmeta`** — the Page Top / Page Bottom sections live in postmeta, so a hero image gets synced too. **Plain-string values only**, never inside serialized/JSON blobs. `srcset`/`sizes`/captions left alone.
- Writes via raw `$wpdb` (no `save_post` side effects / no meta loop) and `clean_post_cache()`s each edited post.

---

## CSS Architecture

One CSS file per feature/component. All loaded conditionally based on what's on the page.
Site-specific overrides go in `style-site.css` in the child theme.

### style-site.css is COMPILED — it is never served directly

`bp_build_site_css()` (in `functions-style-sheets.php`, on `after_setup_theme`) compiles `style-site.css` → `dist/site.css` + `dist/site.min.css`. The browser is served **`dist/site.min.css?ver=_BP_VERSION`** (the `battleplanweb` admin user gets the unminified `dist/site.css`). Editing `style-site.css` alone is not enough to see changes:
- **Rebuild is mtime-gated** — `dist/` only regenerates when `style-site.css` is newer than the dist files. (Same pattern for core CSS and for `.min.js`.)
- **Cache-bust is `_BP_VERSION` only** — the enqueue URL's `?ver=` is the theme version, so once EverCache/Cloudflare cache `dist/site.min.css`, edits won't appear until the cache is purged or the version bumps.

So after changing `style-site.css`: ensure the dist rebuilds (touch/upload so its mtime is newest, or delete `dist/site.min.css`), then **purge EverCache + Cloudflare**. To verify quickly, log in as `battleplanweb` (served the unminified, freshly-rebuilt `dist/site.css`). If a site "ignores" your CSS, a stale compiled `dist/site.min.css` is the first suspect.

### Page content has NO comment syntax

Content pasted into the editor / Page Top / Page Bottom / Elements is shortcode + HTML only. `/* … */` and `// …` are **not** stripped — they render as literal text on the page. When delivering paste-in markup (e.g. a `page-content.txt`), keep all labels/instructions OUTSIDE the copyable blocks; never put comments inside a block the user will paste.

Key CSS variable groups (defined in `:root` in `style-site.css`):
- `--font-primary`, `--font-secondary`, `--font-tertiary`, `--font-text`
- Color palette: `--main-red`, `--main-blue`, `--black`, `--white`, `--light-grey`, etc.

### Conventions for `style-site.css` when designing pages

The base `style-site.css` ships with a fixed skeleton of empty selectors (Header, Content, Sections, Footer, Icons & Social, Site Navigation, Mobile Styles, etc.). When building out a site, **fill in those selectors — don't add parallel ones**.

**1. Reusable strips → `[section style="N"]` + `.section.style-N`**

The `style-N` system is the framework's mechanism for reusable strip backgrounds. Whenever a section's background/treatment will repeat (hero band, brand band, contact band, content band, etc.), define it on `.section.style-1`, `.section.style-2`, etc. — never invent ad-hoc class names like `.icy-bg`, `.brand-band-bg`, or `.section.hero` for the background.

```html
[section name="hero" style="1"] … [/section]
[section name="contact-band" style="1"] … [/section]   ← reuses style-1
```

```css
.section.style-1 { background: linear-gradient(...); ... }   /* the reusable look */
.section.style-2 { background: #fff; }
.section.style-3 { background: <brand band>; }
```

**2. Per-section layout → `#section-name`**

`[section name="foo"]` produces `id="foo"` on the `<section>`. Use that ID to scope per-section padding, headline sizes, image positioning, etc. Do **not** wrap section-specific styles in `.section.foo {}` — that class doesn't exist; the framework only emits `id` from the `name` attr.

```css
#hero            { padding: 60px 0 40px }
#hero h1         { font-size: 64px; ... }
#hero .hero-sub  { ... }
```

**3. Header / Footer / Sections / Icons → fill the existing stubs**

The base `style-site.css` already contains empty selectors like `.top-strip {}`, `.logo-strip {}`, `.logo {}`, `.menu-strip {}`, `#colophon {}`, `.section.style-1 {}`, etc. Fill these in directly. Don't create a second block (e.g. `.section.top-strip {}`) — it duplicates what's already there.

**4. Component-level classes inside cols/groups stay as classes**

`.service-card`, `.feature-card`, `.stat-card`, `.contact-card`, etc. are component-level (passed via `class=""` on `[col]` / `[group]`) — those *are* class-based and live under their parent ID:

```css
#services .service-card { ... }
#hero-stats .stat-card  { ... }
```

**5. Mobile media queries follow the same selectors**

The same `#section-name` and `.section.style-N` selectors carry into the `@media` blocks at the bottom of `style-site.css`. Don't restate them as `.section.foo` in mobile.

**6. Hero text container — always use this base block**

When styling a hero text container (the `[col]` that holds the headline/subhead/buttons inside a `[parallax]` or hero `[section]`), always include padding, an auto margin, and a max-width so the text sits in a readable block over the background image:

```css
.home-hero .hero-text {
	text-align: left;
	padding: 1em 2em;
	margin: 0 auto;
	width: 100%;
	max-width: 600px;
}
```

Adjust `max-width` per design (typically 500–700px). Don't omit padding/margin/max-width — text running edge-to-edge over a parallax background is the wrong default.

### Rendered HTML — what your CSS selectors must match

Write selectors against the HTML the shortcodes actually emit, not against the shortcode names. Verified output:

| Shortcode | Renders to |
|---|---|
| `[section name="x" style="2" width="full"]…[/section]` | `<section id="x" class="section style-2 section-full …">…</section>` — **content sits directly inside `<section>`; there is no inner wrapper.** `name` → `id` (spaces/underscores → hyphens). |
| `[layout grid="5e"]…[/layout]` | `<div class="flex grid-5e …">…</div>` — column widths come from the framework's `.grid-*` rules. |
| `[col class="c"]…[/col]` | `<div class="col c …"><div class="col-inner">…</div></div>` — **everything you put in a `[col]` lands inside `.col-inner`.** |
| `[nested grid="g"]` | `<div class="flex nested grid-g …">` |
| `[txt class="c"]…[/txt]` | `<div class="block block-text span-100 c">…</div>` |
| `[group class="c"]…[/group]` | `<div class="block block-group span-100 c">…</div>` |
| `[btn class="c"]Label[/btn]` | `<div class="block block-button span-100 c"><a class="button c">Label</a></div>` — the class lands on **both** the wrapper div and the `<a>`. |
| `[img class="c"]` | `<div class="block block-image span-100 c">…</div>` |
| `[get-icon type="t"]` | `<span class="icon t"><svg class="icon-svg icon-t">…</svg></span>` — SVG fills via `currentColor`, so set `color` (not `fill`). |
| `[get-icon type="t" grid="1-2"]TXT[/get-icon]` | `<div class="icon-card"><div><span class="icon t">…</span></div><div>TXT</div></div>` — **one `.icon-card` per call.** Wrap a row of them in your own `<div class="icon-cards">` and grid that. |

Practical consequences:
- Per-section styling hangs off `#section-name` (from `name`); reusable strip backgrounds off `.section.style-N`. There is **no** `.section.{name}` class.
- A component class on a `[col]` (`.service-card`, etc.) is a descendant of `.col-inner`, so `#services .service-card` works; `#services > .service-card` does not.
- Loose siblings (e.g. `[get-icon]` + a `<span>` label) are **separate** children — wrap each icon+label pair in its own `<div>` before laying a row out in a grid, or the grid splits icons from text.

---

## JavaScript Architecture

One JS file per feature (`script-carousel.js`, `script-magic-menu.js`, etc.), each with a `.min.js` version.
Site-specific JS goes in `script-site.js` in the child theme.
Always vanilla JS — never jQuery.

---

## JS Minification

Node.js is **not** in the system PATH. Use Adobe Dreamweaver's bundled Node:

**Node:** `C:/Program Files/Adobe/Adobe Dreamweaver 2021/node/node.exe`

**UglifyJS** (self-contained, no npm needed) is stored under the current Windows user's AppData. On this machine:
`C:/Users/Glendon Guttenfelder/AppData/Local/Temp/terser_install/uglify_pkg/package/tools/node.js`

If your Windows username differs, swap in `%USERPROFILE%/AppData/Local/Temp/terser_install/uglify_pkg/package/tools/node.js`.

If that temp folder is gone, re-download:
```
https://registry.npmjs.org/uglify-js/-/uglify-js-3.19.3.tgz
```
Extract and use `tools/node` as the require path.

**Minification options to use:**
```js
const UglifyJS = require('...path above...');
const result = UglifyJS.minify({ 'filename.js': code }, {
  mangle: true,
  compress: { drop_console: false, dead_code: true, unused: false },
  output: { comments: false }
});
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

---

## Jobsite GEO System

A custom system for service businesses to publish real job entries that automatically generate SEO landing pages by service type and location.

### What It Does
Each jobsite entry (a real completed job) automatically:
1. Creates a taxonomy term for the city/state (`jobsite_geo-service-areas`)
2. Creates a taxonomy term for service type + location (`jobsite_geo-services`) — driven by AI classification
3. Tags the technician who did the job (`jobsite_geo-techs`)
4. Geocodes the address via Google Places API → stores lat/lng for map display
5. Links to a matching testimonial (matched by customer name via `_bp_match_key`)
6. Sends email notification to client and/or battleplanweb

The taxonomy archive pages become the SEO landing pages — e.g.:
- `/service-area/allen-tx/` → all jobs in Allen, TX
- `/service/air-conditioner-repair--allen-tx/` → AC repair jobs in Allen, TX

### Enabling Jobsite GEO
In `functions-site.php`, uncomment and configure:
```php
update_option('jobsite_geo', array(
    'install'         => 'true',
    'media_library'   => 'limited',  // 'limited' = only show own uploads; 'all' = full library
    'pin_anchor_x'    => '30',
    'pin_anchor_y'    => '56',
    'default_state'   => 'TX',
    'notify'          => 'false',    // or email address to notify on new jobsite
    'copy_me'         => 'true',     // also send notification to battleplanweb
    'default_service' => 'HVAC Services',
    'token'           => '',         // API token for webhook auth (Housecall Pro)
    'fsm_brand'       => '',         // 'Housecall Pro' to enable HCP webhook
));
```

### Custom Post Type: `jobsite_geo`
- Public archive at `/jobsites/`
- No create capability for regular users (managed by roles)
- Sidebar auto-removed on archive/taxonomy pages

**ACF Fields on each jobsite post:**
| Field | Type | Notes |
|---|---|---|
| `job_date` | date | Required |
| `address` | text | Required |
| `city` | text | Required |
| `state` | text | Required, defaults to `default_state` |
| `zip` | text | Required |
| `jobsite_photo_1–4` | image | Returns attachment ID |
| `jobsite_photo_1–4_alt` | text | Caption = alt text; required when photo is set |
| `is_priority_job` | checkbox | Boosts score for display priority |
| `review` | post_object | Links to matching testimonials CPT post |

### Taxonomies
| Taxonomy | Slug | Rewrite | Purpose |
|---|---|---|---|
| `jobsite_geo-service-areas` | `service-area` | `/service-area/{city-state}/` | City-level landing pages |
| `jobsite_geo-services` | `service` | `/service/{service--city-state}/` | Service + location landing pages |
| `jobsite_geo-techs` | `tech` | `/tech/{name}/` | Per-technician job listing |

**Services taxonomy slug format:** `{service-slug}--{city-slug}-{state}` (double dash separates service from location). New terms are generated in this canonical form by `bp_geo_assign_taxonomy_term()` and `bp_geo_sync_services_from_types()`. To bring an older site up to date, run **Jobsites → ⚙️ Taxonomy Cleanup** (`bp_geo_run_taxonomy_cleanup()`), which refreshes tags and canonicalizes/merges all three taxonomies in one sweep.

### SEO: only `/service/` is indexable
Only the combined **`/service/{service--city-st}/`** pages target a specific service-in-town and are meant to rank. The other two jobsite taxonomies are too vague, so they are **301-redirected** (in `includes-jobsite-geo.php`, on `template_redirect`) to the most-populated `/service/` page for that context, falling back to the `/jobsites/` archive:
- `/service-area/{city-st}/` → best service page in that city (`bp_geo_best_service_for_area()`)
- `/service-type/{service}/` → best city page for that service (`bp_geo_best_service_for_type()`)
- Legacy bare-city URLs (`/{city-st}/`) redirect straight to the service page too (no chain).

Both taxonomies are also excluded from the Yoast sitemap (`battleplan_sitemap_exclude_taxonomy` in `functions.php`) and set to `noindex` (housekeeping Yoast settings) — but the 301 is what actively de-indexes them and passes link equity to the service page.

### SEO Landing Page Content System
The archive template (`archive-jobsite_geo.php`) displays a content snippet above the map and job cards. This snippet is stored as **term meta on the `jobsite_geo-services` taxonomy term**.

When a new service+city combination is created for the first time, AI (Claude via Anthropic API) automatically generates a content snippet for that landing page and saves it to the term's meta data.

Page title is dynamically set: `"Air Conditioner Repair in Allen, TX · Business Name"`
Meta description is also generated and stored on the term.

> **Note:** An older approach used `landing` CPT posts (titled "City, State" or slug `jobsite-geo-default`) to inject content. That system has been replaced by AI-generated term meta. The archive template code (`archive-jobsite_geo.php`) reads from `$GLOBALS['jobsite_geo-content']` which is populated by the term meta approach.

### Scoring System
Posts are sorted on archive pages by score (highest first):

| Condition | Points |
|---|---|
| Has linked testimonial | +25 |
| Marked as priority job | +25 |
| Description ≥ 300 chars | +15 |
| Description ≥ 150 chars | +10 |
| Description ≥ 75 chars | +5 |
| Description < 75 chars | -10 |
| Each keyword match (repair/replace/install/service/etc.) | +2 (max +10) |
| Each photo (up to 4) | +10 |
| Posted ≤ 3 days ago | +50 |
| Posted ≤ 7 days ago | +25 |
| Posted ≤ 30 days ago | +10 |

### Shortcodes (available on jobsite landing pages)
```
[get-jobsite type="city"]    → outputs city name on current landing page
[get-jobsite type="state"]   → outputs state abbreviation

[get-service default="HVAC Services"
             air-conditioner-repair="AC Repair"
             heating-repair="Heating Repair"]
```
`[get-service]` outputs the attribute matching the current service taxonomy slug, or the `default` value.

### API Integrations (`includes-jobsite-geo-api.php`)

**Housecall Pro webhook:**
- Endpoint: `POST /wp-json/hcpro/v1/job-callback?token={token}`
- Publishes a job when a note starting with `***` exists
- Photo captions come from notes formatted as `Photo 1: Caption text`
- Up to 4 photos pulled from job attachments

**Company Cam:**
- Also integrated; force-publishes on update; uses title-based fallback lookup

**Ingestion pipeline (`bp_ingest_jobsite`):**
- Deduplicates by external ID (meta key) or title
- Downloads and deduplicates photos by MD5 hash
- Stores hash in `_bp_file_hash` meta for fast future lookups

### AI Integration
- `bp_geo_assign_taxonomy_term` — AI-driven service classification (assigns `jobsite_geo-services` term)
- **AI Rewriter meta box** (`bp_geo_ai_rewrite`) — appears on jobsite edit screen, positioned just below the Publish box; rewrites job description for SEO

### User Roles
- `bp_jobsite_geo` — field technician; can create/edit own jobsite posts; username auto-tagged as tech
- `bp_jobsite_geo_mgr` — manager; broader access

### The Mobile App
Located at `D:/00 - Battle Plan Assets/bp-geo-app/`
A **PWA (Progressive Web App)** built and maintained with Claude's assistance.
- Self-contained — all app code lives in a single `index.html`
- Deployed and hosted on **Cloudflare Pages**
- Has `manifest.json` and `sw.js` service worker for installability
- Allows technicians to post jobsites from their phones in the field

### Photo Handling
- EXIF orientation auto-corrected on upload
- Photos renamed: `jobsite_geo-{post_id}--{original_name}`
- First photo auto-set as featured image
- Admin has rotate button (AJAX) for fixing orientation post-upload
- Duplicate detection via MD5 hash (`_bp_file_hash` meta)
- Photos auto-categorized as "Jobsite GEO" in Media Library

---

## Available `includes/` Modules (opt-in)
- `includes-hvac.php` — HVAC-specific functionality
- `includes-jobsite-geo.php` / `-api.php` — Jobsite location tracking
- `includes-carte-du-jour.php` — Restaurant menu system
- `includes-events.php` — Events calendar
- `includes-pedigree.php` — Dog breeding/pedigree
- `includes-timeline.php` — Timeline component
- `includes-user-profiles.php` — User profile system
- `includes-woocommerce.php` — WooCommerce integration
- `include-hvac-products/` — HVAC product data by brand

## Pre-built `pages/` Templates (universal — load with [get-universal-page])
- `page-privacy-policy.php`
- `page-terms-conditions.php`
- `page-accessibility-policy.php`
- `page-review.php`
- `page-profile.php` / `page-profile-directory.php`
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
