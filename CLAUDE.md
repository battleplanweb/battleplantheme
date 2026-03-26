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
- Child theme folder on desktop: `G:/Battle Plan Web/{A-Z}/{Client}/battleplantheme-site/`
- Base/template child theme: `G:/Battle Plan Web/00 - Battle Plan Assets/theme/battleplantheme-site/`

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
├── functions-icons.php            # Icon system
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
1. Copy `G:/Battle Plan Web/00 - Battle Plan Assets/theme/battleplantheme-site/` to the client folder
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
[layout name="" grid="1" gap="" break="" valign="" class=""]
```
- `grid` → number of columns (e.g. `2`, `3`, `1-2`, `2-1`) or CSS value (`300px 1fr`)
- `break` → responsive breakpoint class
- `valign` → vertical alignment

### [nested] — Nested flex grid (inside a layout)
```
[nested name="" grid="1" break="" valign="" class=""]
```

### [col] — Column inside a layout
```
[col name="" class="" order="" break="" align="" valign="" h-span="" v-span="" background="" css="" hash="" gap="" start="" end="" track=""]
```
- `h-span`/`v-span` → CSS grid span values
- `order` → CSS order override

### [group] / [txt] — Block wrapper (div.block.block-group/text)
```
[group size="100" class="" order="" start="" end="" track=""]
```

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
[get-icon type="phone" class="" top="0" left="0"]
```

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
| `form` | "Service Request" | CF7 Quote Request Form | 4 | top | Hidden on 404, contact, review pages |
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

## CSS Architecture

One CSS file per feature/component. All loaded conditionally based on what's on the page.
Site-specific overrides go in `style-site.css` in the child theme.

Key CSS variable groups (defined in `:root` in `style-site.css`):
- `--font-primary`, `--font-secondary`, `--font-tertiary`, `--font-text`
- Color palette: `--main-red`, `--main-blue`, `--black`, `--white`, `--light-grey`, etc.

---

## JavaScript Architecture

One JS file per feature (`script-carousel.js`, `script-magic-menu.js`, etc.), each with a `.min.js` version.
Site-specific JS goes in `script-site.js` in the child theme.
Always vanilla JS — never jQuery.

---

## JS Minification

Node.js is **not** in the system PATH. Use Adobe Dreamweaver's bundled Node:

**Node:** `C:/Program Files/Adobe/Adobe Dreamweaver 2021/node/node.exe`

**UglifyJS** (self-contained, no npm needed) is stored at:
`C:/Users/info/AppData/Local/Temp/terser_install/uglify_pkg/package/tools/node`

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

**Services taxonomy slug format:** `{service-slug}--{city-slug}-{state}` (double dash separates service from location)

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
Located at `G:/Battle Plan Web/00 - Battle Plan Assets/bp-geo-app/`
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
