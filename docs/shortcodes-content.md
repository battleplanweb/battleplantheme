# Battle Plan Theme — Content shortcodes

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

## Content Shortcodes (`functions-shortcodes.php`)

### [get-biz] — Pull business info from customer_info
```
[get-biz info="name"]
[get-biz info="name-short"]     → name minus a trailing ", LLC" / " Inc." / " PLLC" — use in headlines
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

**Mobile: the strip becomes a swipe carousel (default, ≤860px).** A four-photo strip squeezed onto a phone screen is four thumbnails, so below 860px the shortcode adds `.swipe` and the row turns into a native scroll-snap carousel — photos keep a real size (equal height, natural width) and the visitor swipes left/right. Same engine as `[layout scroll=""]`: [style-scroller.css](style-scroller.css) + [style-indicators.css](style-indicators.css) + [js/script-scroller.js](js/script-scroller.js), loaded on demand. Dots are `.carousel-indicators`, shared with `[get-post-slider]`, and only appear once the strip really overflows.

Swipe is skipped — the strip squeezes/breaks the old way — when **`full` is set** (that layout is deliberately stacked; `full="true"` is the way to opt a strip out without designating a full-width image), when **`break`** is anything but the default `none` (an explicit stack request), or with a single image.

Slide height is `--sbs-slide-height` on `ul.side-by-side.swipe` (default `clamp(13em, 58vw, 25em)`) — override per site in `style-site.css`.

### [get-before-after] — Before/After comparison with overlay
```
[get-before-after before="/wp-content/uploads/before.webp" after="/wp-content/uploads/after.webp"
                  before-label="Before" after-label="After" before-alt="" after-alt="" size="large" align="" class=""]
```
Two images side by side under a CSS overlay: a center circle + chevron and BEFORE/AFTER corner tabs. `before`/`after` accept **either** an attachment ID **or** a URL — local/root-relative URLs are resolved back to the attachment (so you get srcset + width/height); anything unresolved falls back to a plain `<img>`. The two photos are height-equalized via flex-stretch + `object-fit:cover`, so mismatched sizes still line up (matching aspect ratios crop least).
- **Alt text:** leave `before-alt`/`after-alt` empty to auto-use each image's **Media Library alt** (`_wp_attachment_image_alt`); pass them only to override.
- `align` → adds an `align-{value}` class to the wrapper (e.g. `align="center"` → `.before-after.align-center`); style per site.

Styling is **variable-driven** on `.before-after` — theme per site by setting these in `style-site.css` (defaults in framework `style.css`):
`--ba-before-bg` / `--ba-after-bg` (tab colors), `--ba-arrow-color`, `--ba-circle-bg`, `--ba-circle-size`, `--ba-tab-inset`, `--ba-tab-font`, `--ba-gap`, `--ba-radius`.

### [get-icon] — SVG icon
```
[get-icon type="phone" class="" top="0" left="0" link="" sr="" new-tab="" before="" after="" grid=""]
```
- `before` / `after` → text rendered inside the icon span, before/after the SVG
- `link` → wraps the icon in `<a class="icon-btn">` (pair with `sr` for screen-reader text and `new-tab` to open in a new tab)
- `grid` → if set with content, splits into a 2-col layout: icon on the left, content (auto-wrapped in `[txt]`) on the right. Empty content falls back to icon-only output.

**⚠️ `[get-icon]` does NOT run `do_shortcode()` on its own content.** `battleplan_getIcon()` drops `$content` into the `.icon-card` verbatim, so a shortcode written inside `[get-icon grid="…"]…[/get-icon]` **prints literally on the page** — `[get-biz info="phone-link"]` renders as those exact characters. Pure HTML inside is fine; a shortcode is not. When the row needs a shortcode (a tracked phone link, `[get-years]`, `[get-biz]`), drop `grid` and write the pair as loose siblings in a plain `<div>` instead — which is the documented shape for an icon + label anyway:

```
<div class="contact-row">[get-icon type="phone"][get-biz info="phone-link"]</div>
```

**⚠️ In a content field that uses BOTH forms, CLOSE EVERY `[get-icon]` — even the icon-only ones.** Write `[get-icon type="star"][/get-icon]`, not `[get-icon type="star"]`. WordPress's shortcode regex makes the closing tag **optional but preferred**: its content group is possessive (`[^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+`), so it swallows every `[` that is not `[/get-icon]` and **cannot backtrack**. A bare `[get-icon]` therefore pairs with the *next* `[/get-icon]` found **anywhere later in the same field**, eating all the markup in between.

Live case (Prosper Air home page, Sept 2026): a Page Bottom field held four service cards using the icon-only form and, further down, an About section using `[get-icon grid="1-3"]…[/get-icon]`. The bare `[get-icon type="snowflake"]` in card one closed on the About section's first `[/get-icon]` — cards 2–4 **and the entire About section** rendered inside card one, and the About band vanished. Nothing errors; the content just disappears.

`battleplan_getIcon()` returns icon-only output whenever `grid` is unset or `$content` is empty, so the closed form is a free no-op. Audit a field with:

```python
import re
opens  = len(re.findall(r'\[get-icon[^\]]*\]', markup))
closes = markup.count('[/get-icon]')
assert opens == closes, f'{opens} [get-icon] vs {closes} [/get-icon]'
```

The same rule applies to any shortcode registered with both a self-closing and an enclosing form. `[get-icon]` is the one in daily use.

**Use `grid` for icon + stacked-text card patterns** (stat cards, feature pills, contact rows — "icon on the left, one or two lines of text on the right"). `[get-icon type="x" grid="1-3"]<text>[/get-icon]` builds the whole icon+text card in one shortcode. The `grid` value is the icon-col : text-col ratio (`1-2`, `1-3`, …) — tune it to taste.

**To lay a row of them out, give each its own `[col]` in a `[layout grid="Ne"]` — never a wrapping `<div>`.** Let the layout grid form the row; each feature is one `[col]` holding a single `[get-icon grid]`:

```
[layout grid="4e" valign="center"]
 [col class="cta-features"][get-icon type="checkmark-seal" grid="1-3"]<span><strong>FREE</strong><br>On-Site Estimates</span>[/get-icon][/col]
 [col class="cta-features"][get-icon type="handshake" grid="1-3"]<span><strong>Honest,</strong><br>Upfront Pricing</span>[/get-icon][/col]
 [col class="cta-features"][get-icon type="shield" grid="1-3"]<span><strong>Licensed</strong><br>&amp; Insured</span>[/get-icon][/col]
 [col class="cta-features"][get-icon type="ribbon" grid="1-3"]<span><strong>100% Satisfaction</strong><br>Guaranteed</span>[/get-icon][/col]
[/layout]
```

Do **not** hand-build the row as `<div class="features"><div class="feature">[get-icon]…</div>…</div>` inside a single `[col]`. That fakes a grid the `[layout]`/`[col]` system already gives you — the same mistake as wrapping a card in a redundant div. **A row of repeated items = one `[col]` each in a `[layout grid="Ne"]`; let the grid do the layout, not stacked `<div>`s.**

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

### [footer-menu] — Plain list of the main menu (for footer "Quick Links")
```
[footer-menu]                          → <ul><li><a> of the MAIN menu's top-level items
[footer-menu location="footer-menu"]   → pull from a specific registered menu location instead
[footer-menu depth="2"]                → include sub-items (default 1 = top-level only)
[footer-menu class="two-col"]          → extra class(es) on the <ul>
```
Emits a lean `<ul class="footer-menu">` (no walker/container), so a footer column stays in sync with **Appearance → Menus** automatically. Use `[get-menu]` for the full styled nav; use this for footer/quick-links lists.

**It always renders something if the site has a menu at all.** Resolution order (`battleplan_footerMenu()` in [functions.php](functions.php)):

1. The `location` attribute, *if* a menu is assigned there. A named-but-unassigned location is treated as a preference, not a hard requirement — it falls through rather than rendering nothing.
2. The primary menu, resolved exactly the way `header.php` does: `widget-menu` > `top-menu` > `header-menu` (last assigned wins).
3. `manual-menu` — the location `[get-menu]` renders. Sites that place their nav with `[get-menu]` assign *only* this one, so without this step the shortcode returns empty on exactly those builds.
4. Any other assigned location, `footer-menu` considered last (the documented default is the MAIN menu; `location="footer-menu"` is the explicit opt-in).
5. No location assigned anywhere → the first menu in **Appearance → Menus** that actually has items.

Only a site with no non-empty menus at all produces no output.

### [add-search-btn] — Add search button to menu or other areas
```
[add-search-btn]
```

---

## Additional Shortcodes

### [btn] — Button
```
[btn link="/page/" align="center" size="100" class="" fancy="false" icon="false"
     new-tab="false" get-biz="" ada="" start="" end="" track="" click="" onclick=""]Button Text[/btn]
```
- `size` → **the button's width in the 12-column grid** — `"1/2"`, `"6"` and `"50"` all emit `.span-6`; the default `"100"` emits `.span-12`. Full list: `convertSize()` in [functions.php](functions.php).

**⚠️ To put two buttons side by side, give each `size="1/2"` — never `grid-template-columns`.** `[col]`'s `.col-inner` is *already* a 12-column grid (`style-grid.css`), and `.col-inner > *` defaults to `grid-column: span 12`, which is what stacks blocks one per row. Writing a `display:grid; grid-template-columns:1fr 1fr` override on the column does not help: the two `.block-button`s still carry `span-12`, which in a 2-track grid clamps to the full row, so they stay stacked.

Two more things that break the row:
- **Do not wrap the buttons in a `[txt]`.** That puts both inside one `.block-text.span-12`, so the grid sees a single child. The `[btn]`s must be *direct* children of the `[col]`.
- `a.button` is `inline-block`, so each button is only as wide as its label and sits centred in its half (`align="left"` / `"right"` moves it). Add `width:100%` in style-site.css when the pair should be equal width.

```
[col class="hero-btns"]
 [btn size="1/2" link="/contact/" icon="arrow-right"]Schedule Service[/btn]
 [btn size="1/2" link="/estimate/" class="ghost"]Get A Free Estimate[/btn]
[/col]
```

On phones, override the span rather than the width — `.block-button { grid-column: span 12 }` — or the two halves just get narrower instead of stacking.

- `fancy` → adds fancy button style (value = style variant, e.g. `"1"`)
- `icon` → icon slug (e.g. `"chevron-right"`) appended to button text
- `before` → `"true"` puts icon before text
- `graphic` → image filename from uploads, shown as button icon
- `get-biz` → pulls link from customer_info field
- `ada` → adds screen-reader-only text for accessibility
- `track` → **impression** tracking: sets `data-track`, fires `track-{value}` once the button scrolls into view
- `click` → **click** tracking: puts `.track-clicks` + `data-action` on the `<a>`, firing `conversion-{value}` on click. Use for CTAs you want counted as conversions — `[btn link="/contact/" click="book-appointment"]` → `conversion-book-appointment`

### Conversion tracking — name it `conversion-*` and it reports itself

The analytics cron (`bp_ga4_collect_achievements_history()` in [functions-chron-analytics.php](functions-chron-analytics.php)) harvests **any** GA4 `unlock_achievement` whose `achievement_id` starts with `conversion-` (or `track-`) — so a new conversion needs **zero GA4 configuration**. Name it correctly and it appears in the dashboard on the next nightly run.

What fires automatically, fleet-wide (all in [js/script-tracking.js](js/script-tracking.js)):
| Event | Source |
|---|---|
| `conversion-phone-call` | `[get-biz info="phone-link" / "area-phone" / "mm-bar-phone"]` — the phone shortcodes emit `.track-clicks` themselves |
| `conversion-form-{form-id}` | successful form send — listens for the `bp:form:sent` CustomEvent dispatched by [js/script-forms.js](js/script-forms.js), reads the form's `data-form-id` |
| `conversion-form-open` | `.modal-btn` — the primary-form modal opener (mobile bar contact button, any `modal-btn`) |

**A hand-written `<a href="tel:…">` is invisible to all of this.** Only the `[get-biz]` phone shortcodes carry `.track-clicks` — which is the usual reason a site under-reports calls. Same for buttons: use `[btn]` with `click=""`, not a raw `<a>`.

To track anything else, put `class="track-clicks" data-action="whatever"` on it — no JS needed, the existing listener picks it up.

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
- **FAQPage schema is automatic:** on the page with slug `faq`, every accordion whose title ends in `?` becomes a Question/Answer in a footer JSON-LD block (answer = rendered body, tags stripped). Other pages opt in with `add_filter('bp_faq_schema', fn($on) => $on || is_page('slug'))`.

### [widget] — Sidebar widget
```
[widget type="basic" title="hide" priority="2" lock="none" set="none"
         class="" show="" hide="" start="" end="" track="" empty="hide"]content[/widget]
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
- `empty="hide"` (default) → if the widget's content renders to nothing, the whole widget is dropped instead of leaving an empty box. This is what lets you stack three `[event_teasers]` widgets at `offset="0/1/2"` — with only one event, widgets 2 and 3 disappear on their own. Use `empty="show"` to force an empty widget to render anyway (`filler` is always exempt).

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
- `orderby` and `order_by` are interchangeable on every shortcode that takes one (`bp_orderby_atts()` in functions.php) — new shortcodes with an order attribute should call it before `shortcode_atts()`

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

### Writing SEO titles & descriptions (audits) — use shortcodes, never hard-code

The framework SEO module runs shortcodes on stored SEO title/description values and then strips any HTML (`bp_seo_render_value()` in [includes/includes-seo.php](includes/includes-seo.php) — resolve `%%vars%%` → `do_shortcode()` → `wp_strip_all_tags()`). So a `[get-biz]` shortcode resolves at render time, and even a tracked phone *link* collapses to just its number. **When authoring SEO titles and meta descriptions during a site audit, use these shortcodes instead of typing the values in — the meta stays correct if the business's city, phone, brand, or founding date ever changes.**

| Element | Use | Renders |
|---|---|---|
| City | `[get-biz info="city"]` | e.g. `Alba` |
| State | `[get-biz info="state-abbr"]` | e.g. `TX` |
| Phone | `[get-biz info="area-phone"]` | formatted number, link stripped to text (e.g. `(469) 230-0781`) |
| Years of experience | `[get-years start="YYYY"]` | e.g. `25 years` — **always ASK for the start year each audit; don't infer it** (see below) |
| Primary brand | `[get-biz info="site-brand" array="0"]` | first `site-brand` entry |

**✨ Write with AI** (editor SEO box, beside SEO Title and Meta Description): reads the page (title, editor body including unsaved edits, Page Top/Bottom) and writes the value using these tokens/shortcodes. It measures the *rendered* length (title ≤ 60, description ≤ 155) and returns the draft for a rewrite (up to 3 drafts) if it runs long or types out the business name, city, or phone instead of using the token. It never uses `[get-years]` (see below). `%%sep%%` is for titles only; descriptions are sentences separated by periods. Em/en dashes are forbidden in the prompt, and any that slip through are replaced after generation (`bp_seo_ai_dedash()`): a comma in titles; in descriptions, a period when a capital letter follows, otherwise a comma. It only fills the field, so nothing saves until **Update**. The live `52 / 60` counters beside each label count rendered characters too. Model: `BP_SEO_AI_MODEL` (default `claude-sonnet-4-6`); it uses the same API key as AI alt text and is hidden when there's no key.

Two gotchas:
- **`site-brand` values are often lowercase slugs** (`amana`, `goodman`) because they double as logo-file keys. `[get-biz info="site-brand" array="0"]` outputs them verbatim, so it may render lowercase in a title — check the site's `site-brand` values and, if they're slugs, type the brand name directly instead.
- **Never infer the experience start year — ASK every audit.** `customer_info['year']` is unreliable for this: it may be the *website* launch, the business's founding, or unrelated to the experience being advertised (a brand-new company can legitimately claim the owner's decades in the trade). There's usually a real business start date, but sometimes the number comes from the owner's personal experience. Because you can't tell which from the config, **ask the operator for the correct `start` year on each audit before writing an experience claim.** `[get-years start="…"]` counts to the current year and auto-updates, which is exactly why the anchor year has to be right.

---

### [get-translator] — Language switch (Google Translate)

```
[get-translator align="center" class="" from="English" to="Español"]
```

Renders an on-brand **segmented language switch**, not Google's widget. Google's gadget, banner and branding are never shown: the control writes the `googtrans` cookie and reloads, and Google's element — mounted off-screen — applies the translation on the way back in. Languages are `en`/`es` (`language` and the legacy `text` attribute are accepted but ignored).

The shortcode enqueues `script-translator`, inlines `style-translator.css`, emits the nonce'd `element.js` tag, prints the switch plus a screen-reader-hidden `#hablamos-espanol` mount, and registers the footer credit.

**Restyle per site with variables — don't rewrite the rules.** Defaults derive from the site's accent tokens, so it usually matches already:

```css
.bp-translator {
	--translator-active-background:                            var(--main-gold);
	--translator-border-radius:                                0;
	--translator-font-size:                                    13px;
}
```
Full list at the top of [style-translator.css](style-translator.css): color/background, active + hover pairs, border, radius, font family/size/weight, letter-spacing, text-transform, padding.

**Placing it outside page content** — `header.php` has no `do_shortcode` call, so hook it in `functions-site.php`:

```php
add_action('bp_after_masthead', function () {
	echo do_shortcode('[get-translator align="right"]');
});
```

**Attribution:** `bp_translator_credit()` prints "Traducido por Google" on `bp_after_colophon`, and the CSS shows it **only when the page is actually translated** (gated on Google's `.translated-ltr`). Change the wording with the `bp_translator_credit_text` filter.

### How it works, and the traps it already avoids

| Piece | Why it's there |
|---|---|
| `.notranslate` + `translate="no"` on the switch | otherwise Google translates the switch's own labels and "English" becomes "Inglés" |
| `.bp-translator-mount` is clipped, **not** `display:none` | `TranslateElement` needs a real element to build into |
| `.unique` on each half | `button:not(.unique)` in [style-forms.css](style-forms.css) styles **every** `<button>` and re-applies the whole `--button-*-hover` set on hover — the pill grew 180×24.8 → 186×30.8 mid-hover and re-rounded its inner corners. `.unique` is the framework's opt-out; the switch then styles itself entirely |
| cookie written on host, `www` host **and** `.domain` | Google writes all of them; leave one behind and the stale copy wins the next load |
| `location.replace(pathname + search)` | drops any `#googtrans(en|es)` hash, which outranks the cookie, and keeps Back off the pre-switch state |
| `max-age` of a year | Google's own cookie dies with the tab; the visitor's choice should not |
| **never** branch this server-side on `$_COOKIE` | the HTML is page-cached — same class of bug as [[is_mobile breaks the cache]]. The active pill is set in JS |

**Two layout traps (fixed in `style-translator.css` — don't undo them).** They only bite if Google's own UI is ever re-enabled, but the rules are cheap insurance:

1. **Google drops the whole page 40px.** When translation is active it sets `top: 40px` inline on `<body>` to reserve room for its banner. Hiding the banner does **not** give that space back — the offset is on the body. Cancelled with `.translated-ltr body { top: 0 !important }`.
2. **The gadget adds ~33px of height.** `.goog-te-gadget` is a block-level element; negative margins on the inner control move it but never reclaim the parent's height (measured live: a strip went 45.8px → 78.4px the moment the widget built). It is now absolutely positioned out of the flow — and hidden entirely.

Other notes:
- `googleTranslateElementInit` **must stay on `window`** — element.js calls it by name via `?cb=`. The JS polls for the library instead of the old fixed `setTimeout`, which lost the race on slow connections.
- The `style-src` entries for `translate.googleapis.com` / `www.gstatic.com` in [header.php](header.php) are **precautionary**. The current element.js injects its CSS inline and triggers no CSP violation; older builds fetched `translateelement.css`.
- Google can leave `#googtrans(en|es)` in the URL, which is **not a valid CSS selector**. `animateScroll()` in [js/script-pages.js](js/script-pages.js) feeds `location.hash` to `querySelector`, so it's wrapped in try/catch — without it every translated page load throws and the deep-link scroll dies.
- **Migrating an older site:** rules written against `#hablamos-espanol` now target the hidden mount and do nothing. Move that spacing to `.bp-translator`.
