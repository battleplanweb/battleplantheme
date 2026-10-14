# Battle Plan Theme — Layout shortcodes

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

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

**Critical rule — a `[section]` holds a `[layout]`, never bare content.** Every `[section]` must wrap its content in a `[layout]`; do not place `[txt]`, headings, or HTML directly inside a `[section]`. The structure is always **section → layout → col → content**. Content that should span the whole row (a heading above a row of cards, an intro paragraph, etc.) goes in its own `[col class="span-all"]` *inside that same layout* — not in a loose `[txt]` above the `[layout]`.

```
[section name="services" style="2" width="full"]
 [layout grid="5e"]
  [col class="span-all"][txt]
   <p class="eyebrow">Our Services</p>
   <h2>Drainage Solutions That Work</h2>
  [/txt][/col]
  [col class="service-card"][txt] … [/txt][/col]
  …four more service-card cols…
 [/layout]
[/section]
```

### [layout] — Flex grid container
```
[layout name="" grid="1-1" break="" valign="" class=""]
```
- `grid` → column layout. Three notations:
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
  - **Auto-fit notation** (tile rows whose length varies): `grid="fit"`. Sets a *tile width* instead of a column count — as many tiles per row as the space allows, reflowing as it narrows, **with the last row centred**. No breakpoints to maintain, and it measures the **container**, not the viewport, so it stays correct inside a sidebar or a narrow column where `Ne` does not (`Ne` only steps at 860px/576px of *window* width).
    - `grid="fit"` → 260px tiles, as many per row as fit, orphans centred
    - `--fit-min` (default `260px`, declared on `:root`) is the tile width **and** the wrap threshold. `--fit-max` is unset by default, so tiles never grow and a short last row can never render wider than a full one. Set `--fit-max` above `--fit-min` to let rows stretch and fill. Override either in `style-site.css`:
      `#gallery .flex.grid-fit { --fit-min: 300px; --fit-max: 340px; }`
    - Use it for galleries, product rows, jobsite photos — anything where the item count is whatever the client uploaded. Stick with `Ne`/dash notation when the column count **is** the design (a trio of feature boxes should stay a trio, not quietly become 2+1 in a narrow container).
    - **This one is flex, not grid — it has to be.** A grid has one column count for every row, so a short last row always lands in tracks 1..n and `justify-content` centres the whole track set rather than each row; no `grid-template-columns` value can centre orphans. Consequences inside a `grid="fit"`: `grid-column` is inert, so **`.span-4` and friends do nothing** (`.span-all` is translated to `flex: 0 0 100%` for you, so section headings still work), and a site-level `margin: 0 auto` on the tile **breaks the centring** — auto margins absorb free space in flex before `justify-content` runs and spread the row apart. Need real column spans? Use `Ne`/dash notation.
    - At phone widths tiles stay at `--fit-min` and centre rather than going edge to edge. For full-bleed tiles on mobile set `--fit-max: 100%` in a site-level `@media` — but only at a width where just **one** tile fits, or the last row of one balloons to the full width.
  - **Custom track notation** (fixed + fluid mix): pass a raw CSS `grid-template-columns` value. Any `grid` string containing `px`, `em`, or `fr` triggers it — the layout renders as `<div class="flex grid-custom" style="grid-template-columns:…">`.
    - `grid="150px 1fr 200px"` → fixed 150px left rail · fluid center · fixed 200px right rail
    - `grid="1fr 1fr"` → two fluid equal columns
    - Use this when columns aren't simple equal/ratio shares — e.g. decorative side images flanking a fluid content column (the image cols are fixed-width, the text col takes `1fr`). For plain ratio splits, stick with dash/equal notation.
  - **Never use more than 6 columns** in dash/equal notation. Don't write `grid="1-1-1-1-1"` — use `grid="5e"`. Don't write `grid="7e"` or `grid="8e"`. (Custom track notation is exempt — it's for fixed-rail layouts, not wide column counts.)
- `valign` → **vertical** alignment (maps to `align-self` on the cols) — values: `start`, `center`, `end`, `stretch`. Default is `start`. Use **`valign="stretch"`** to make every column equal height (cards in a row line up their tops *and* bottoms — the usual choice for a row of teaser/service cards). `left`/`right` are **not** valign values (those are `align`), and `middle` isn't one either — use `center`.
- `gap` → **do not set this attribute.** Glendon adjusts column spacing via CSS in the rare cases it's needed. Same applies to `[col]`.
- `break` → **do not set this attribute.** Glendon adds responsive breakpoints manually if/when needed. Same applies to `[col]`. (The one exception is `scroll` below, which needs a `break` to know where to kick in.)
- `scroll` → **swipe scroller instead of stacking.** `[layout grid="4e" break="3" scroll="true"]` — at the break point the row stops stacking into one column and becomes a horizontal scroll-snap carousel: the columns keep a usable width and the visitor swipes left/right, with dots underneath. Unset or `scroll="false"` = the usual stacking.
  - Break points are the ones `break-*` already stacks at: `break="4"` → 1280px, `break="3"` → 1024px, `break="2"` → 860px, `break="1"` → 576px. **`scroll` does nothing without `break="1-4"`.**
  - Slide width defaults to 80% of the row (so the next card peeks in). Override with a length instead of `true`: `scroll="65%"`, `scroll="18em"`, `scroll="260px"`.
  - **The row bleeds to the screen edges.** A half-visible slide runs off the physical edge instead of stopping at the content inset (which reads as a mistake); the first and last slides keep the inset so the row still starts and ends on the content line. The script measures it per row — nothing to set.
  - **Every direct `[col]` becomes a slide** — including a `[col class="span-all"]` heading. Put the section heading in its own `[layout]` above the scrolling one.
  - Dots are `.carousel-indicators` (same as `[get-post-slider]`), so styling them once makes both match. Files: [style-scroller.css](style-scroller.css) + [style-indicators.css](style-indicators.css) + [js/script-scroller.js](js/script-scroller.js), all loaded on demand.

**Critical rule — never nest `[layout]` inside `[layout]` (or inside a `[col]`).** It breaks the grid. `[layout]` emits a bare `<div class="flex grid-X">` whose grid CSS only works as the **direct child of a `[section]`** — drop a second `[layout]` inside a `[col]` and the inner grid renders wrong. Two correct options instead:
**Flatten the grid — this is the only option.** Rethink the columns as a single layout. A `grid="1-2"` whose right col holds an inner `grid="1-1"` is really just three columns — write `grid="1-1-1"`. Every "I need a grid inside a grid" case collapses this way, using `h-span`/`v-span` for the spanning cells and the custom track notation when the shares aren't uniform.

Worked example — a hero whose left side is a headline block *above* a 3-across feature strip, with a full-height photo on the right. That reads like "a sub-grid inside the copy column", but it is one flat 4-track grid:

```
[section name="home-hero" width="edge"]
 [layout grid="1fr 1fr 1fr 3fr"]
  [col class="hero-copy" h-span="3"][txt] …headline, lead, buttons… [/txt][/col]
  [col class="hero-img" v-span="2"]<img …>[/col]
  [col class="hero-feature"][get-icon type="a" grid="1-3"]…[/get-icon][/col]
  [col class="hero-feature"][get-icon type="b" grid="1-3"]…[/get-icon][/col]
  [col class="hero-feature"][get-icon type="c" grid="1-3"]…[/get-icon][/col]
 [/layout]
[/section]
```
Auto-placement does the rest: the copy takes row 1 / cols 1–3, the photo takes col 4 and spans both rows, and the three features fill row 1–3 of row 2. Release the spans in the mobile query (`#home-hero .col { grid-column:auto !important; grid-row:auto !important }`) when the row collapses to one column.

### ⚠️ [nested] — DEPRECATED (2026-08-02). Never use it on a new build.

`[nested]` is still registered, but **only as back-compat for legacy page content on three sites** — Bubba's Cooks Country (`#section-menu`, `#section-choose-location`), Goot Bros Music and GuttenBoy — whose sections wrap `[layout]`s inside a `[nested]`. It is deleted from the framework the moment those three are re-authored. **Do not write new `[nested]` markup, and rewrite it whenever you touch a page that has it.**

It was documented as "a sub-grid inside a `[col]`", which **WordPress cannot parse: a shortcode may not be nested inside another shortcode of the same name.** `get_shortcode_regex()` matches the *first* `[/col]` as the outer col's closing tag — its content group is `[^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+`, which stops dead there and (being possessive) cannot backtrack to a later one. So `[col]` → `[nested]` → `[col]` always broke the same way: the outer `[col]` closed on the first inner `[/col]`, the remaining inner cols escaped as loose siblings scattered across the grid, a literal `[/nested] [/col]` printed on the page, and everything after it was pushed out of position.

It *does* work as a direct child of `[layout]` (a grid **row** rather than a sub-grid) — that is the only shape that ever worked, and the only reason it is still registered. **Flatten instead** (see above); for the per-item-row case, give each item one `[col]` holding a plain `<div>` you style with `display:grid` — that is how Firehouse's vendors archive was migrated.

**The same parser rule kills any same-name nesting** — never `[col]` in `[col]`, `[txt]` in `[txt]`, `[section]` in `[section]`, `[layout]` in `[layout]`.

Quick audit for any page's markup — every container tag must have max depth 1:
```python
import re
for tag in ['section','layout','col','txt','img','btn','group']:
    d = mx = 0
    for m in re.finditer(r'\[(/?)' + tag + r'(?=[\s\]])', markup):
        d += -1 if m.group(1) else 1; mx = max(mx, d)
    assert d == 0 and mx <= 1, f'{tag}: depth {mx}, ends {d}'
```

### [col] — Column inside a layout
```
[col name="" class="" order="" break="" align="" valign="" h-span="" v-span="" background="" css="" hash="" start="" end="" track=""]
```
- `h-span="N"` → the col spans **N columns** of the grid (`.col.h-span-N` → `grid-column: span N`). `v-span="N"` → spans **N rows** (`grid-row: span N`). The grid (`.flex.grid-*`) is a real CSS grid, so a single col can span multiple cells in either direction — e.g. in a `grid="1-1"`, give the heading col `h-span="2"` to make it span both columns while the rest of the cells flow normally.
- `class="span-all"` → shorthand for "span the entire row" (`grid-column: 1 / -1`). Prefer this over `h-span="5"`/`h-span="6"` for a full-width header inside a multi-col grid — it's count-agnostic, so it keeps spanning the whole row no matter how many columns the layout has.
- `order` → CSS order override
- `align` → **horizontal** alignment (justify-self) — values: `left`, `center`, `right`.
- `valign` → **vertical** alignment (align-self) — values: `start`, `center`, `end`, `stretch` (not `left`/`right`, and not `middle` — use `center`).
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

(`[group]` is practically deprecated — see below. `[nested]` is deprecated too — see its section above.)

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

**A card IS the `[col]` — don't wrap it in another div.** Put the component class (`.service-card`, `.feature-card`, etc.) directly on the `[col]` and open a `[txt]` inside it. Do **not** write `[col]<div class="service-card">…</div>[/col]` — the extra wrapper is the exact `.col` / `.col-inner` the framework already gives you, just duplicated. An inner grouping div for *part* of the card (e.g. `<div class="service-text">` around the heading+paragraph+button) is fine; it's the *outer* card wrapper that's redundant.

```
[col class="service-card"][txt]
 <div class="service-img"><img src="/wp-content/uploads/teaser-french-drains.webp" width="333" height="219" style="aspect-ratio:333/219" class="wp-image-7187" alt="">[get-icon type="french-drain"]</div>
 <div class="service-text">
  <h3>French Drains</h3>
  <p>The most effective way to redirect groundwater and keep your property dry.</p>
  [btn link="/french-drains/" class="learn-more" icon="arrow-right"]Learn More[/btn]
 </div>
[/txt][/col]
```

NOT:

```
[col][txt]
 <div class="service-card">          ← redundant: this div is just .col-inner again
  <h3>French Drains</h3> …
 </div>
[/txt][/col]
```

**When you flatten a wrapper `<div>`, move its CSS hook with it — don't let it vanish.** If the `<div>` you're removing carried styling (a `class` with a background, a `position:relative` context, a `::before`/`::after` connector or accent, a grid/flex declaration), that class and its CSS have to land on the element that replaces it — usually the `[col]` (via `class=""`) or the `[layout]` (yes, `[layout class="x"]` works → `<div class="flex grid-… x">`). Two traps specifically:
- **Selector drift.** A rule written as `.journey .journey-step` (descendant of the old wrapper) stops matching once the wrapper is gone. Rewrite it against the new DOM — `#section-name .journey-step`, or whatever the surviving ancestor is.
- **Positioned pseudo-elements lose their anchor.** An absolutely-positioned `::before`/`::after` (e.g. a connector line behind a row of icons) needs a `position:relative` ancestor. If that `position:relative` lived on the deleted wrapper, re-add it to the element now carrying the class (commonly the `[layout]`). And if the pseudo-element is positioned from the top of its host, keep section headings in a *separate* `[layout]` so the decorated grid's top still aligns with its own content row, not the heading.

**Linked cards — compose from framework blocks, not one big `<a>` + CSS background.** A clickable teaser/service card is a stack of framework block shortcodes inside the `[col]` — a real linked image, the label, and a real button — each a block, so no outer `[txt]` around the whole col (only the loose inline label needs its own `[txt]`):

```
[layout grid="6e" valign="stretch"]
 [col class="span-all section-head"][txt]<h2>We're Here For All Your HVAC Needs</h2>[/txt][/col]

 [col class="service-card"]
  [img link="/air-conditioning/" ada-hidden="true"]<img src="/wp-content/uploads/service-ac.webp" alt="" width="300" height="300" style="aspect-ratio:300/300" />[/img]
  [txt]<span class="service-icon">[get-icon type="snowflake"]</span><span class="service-label">Air Conditioning</span>[/txt]
  [btn link="/air-conditioning/" ada="about our air conditioning service"]Learn More[/btn]
 [/col]
 …more service-card cols…
[/layout]
```

Why this shape, not a single `<a class="service-link" style="background-image:url(…)">…</a>`:
- **Real `<img>` via `[img]`, never a CSS `background-image`.** Only real `<img>` tags get alt text, width/height, lazy-loading, and — critically — the framework's media-replace / alt-sync / dimension-fix systems (all DB-walkers that look for `<img class="wp-image-…">`, not inline background URLs). A background-image is invisible to all of that.
- **`[img link="/path/"]`** makes the image itself a link (the whole image block, no hand-written `<a>`), and **`ada-hidden="true"` + `alt=""`** hides the decorative image from screen readers since the button already provides the accessible link — otherwise AT users hit the same destination twice.
- **`[btn link="/path/" ada="…"]` carries the real link.** When a row has many identical button labels ("Learn More"), give each a unique **`ada="…"`** (screen-reader-only text) so AT users can tell them apart — five links all reading "Learn More" is an accessibility failure. Pair with **`valign="stretch"`** on the layout so the cards are equal height regardless of label length.

### [img] — Image block wrapper
```
[img size="100" class="" order="" link="" get-biz="" new-tab="" ada-hidden="false" start="" end="" track=""]
```
- `get-biz` → uses `[get-biz info="{value}"]` as the link href

### [vid] — Video block (YouTube, Vimeo, or local)
```
[vid size="100" mobile="100" link="" thumb="" preload="false" related="false" controls="true" autoplay="false" loop="false" muted="false" begin="" start="" end="" track=""]
```

**`autoplay="true"` forces `muted` on** — every browser blocks sound-on autoplay, so the two are never useful apart. You don't need to write `muted="true"` alongside it (doing so is harmless). A video that "won't autoplay" on an older site is almost always missing the `muted` attribute in the rendered tag.

**Any `<button>` you render that is not meant to look like a button needs `class="unique"`.** [style-forms.css](style-forms.css) styles `button:not(.unique)` — **every** `<button>` on the site gets the full `--button-*` set (padding, radius, background, shadow, text-transform) and re-applies the whole `--button-*-hover` set on hover. This bites anything using a button for semantics rather than appearance: the `[yt-channel]` video tiles rendered as rounded, shadowed pills until they carried `.unique`, and the language switcher hit the same wall. `.unique` is the framework's opt-out (Site Pulse uses it on every `.sp-btn`). It does **not** suppress the browser's own default button chrome — reset `appearance`/`border`/`padding` yourself as well. Grep `style-forms.css`, not `style.css`, when hunting global element styling.

**The facade lives in `script-video.js`, enqueued on demand — not in script-pages.js.** `[vid]` prints no iframe: it emits a thumbnail + `data-link`, and the click handler swaps in the real embed. That handler and `stopAllVideos()` used to ride along in script-pages.js on every page of every site; they now sit in `script-video.js` alongside `[yt-channel]`, and both shortcodes enqueue it. **Anything calling `stopAllVideos()` must guard with `typeof stopAllVideos === 'function'`** — on a page with no video the file isn't loaded (the section-lock modal handler in script-pages.js does this).

**Local files: link the `.webm` and ship a matching `-ios.mp4`.** When the `link=` extension is `webm`, the shortcode adds a second `<source>` at `<same-basename>-ios.mp4` as the Safari/iOS fallback — so `composite-vid.webm` needs `composite-vid-ios.mp4` beside it. Encode the pair as **AV1/WebM + H.264/MP4** (AV1 runs ~3x smaller than H.264 at the same quality); see the auto-memory `reference_web_video_encoding` for the ffmpeg recipes. Never HEVC — Chrome and Firefox refuse it.

### [yt-channel] — A whole YouTube channel (includes-video.php)
```
[yt-channel channel="" playlist="" layout="grid" max="12" columns="3" break="2" sort="newest"
            shorts="all" search="" header="false" titles="true" date="true" views="false"
            more="false" schema="true" related="false" begin="" size="100" class="" start="" end="" track=""]
```
`[vid]` embeds one video; this embeds a channel — the job people otherwise reach for Smash Balloon / Embed Plus / YotuWP to do. `channel=` takes an `@handle`, a `UC…` id, or any channel URL.

**Three layouts.** `grid` (each tile becomes its own player), `feature` (big stage on top, thumbnail strip below feeds it), `scroll` (the grid plus `.swipe`, so it becomes a horizontal rail at its break point — same machinery as `[layout scroll="true"]`, dots and all).

**It inherits `[vid]`'s facade rule: no iframe until a click.** Tiles are thumbnails off Google's CDN; `script-video.js` builds the real embed at click time and tags it `.video-player` so `stopAllVideos()` still pauses it. A grid of real embeds would be ~10MB of YouTube JS — never render one.

**Nothing is fetched on the render path.** Everything comes out of a 12h transient (`bp_yt_cache_seconds` filters it) that is *stored* for 30 days, so an over-quota or down API keeps serving the last good payload instead of collapsing the block to an empty div. Refreshes happen behind a 2-minute lock, so a busy page can't stampede the API.

**API key: `BP_YOUTUBE_KEY` in wp-config → `_YOUTUBE_API`, falling back to `_PLACES_API`.** One Google Cloud key answers both once YouTube Data API v3 is enabled on the project, so enabling that API lights this up fleet-wide with no per-site config. **With no key it degrades to YouTube's keyless RSS feed** — which works, but is hard-capped at the 15 newest videos with no durations, playlists or channel stats.

**Never call `search.list`.** It costs 100 quota units against a 10,000/day allowance — it would drain the day in 100 page loads. The uploads playlist off `channels.list` returns the same videos for 1 unit, and that is what this uses.

**`shorts="exclude|only"` is a heuristic, not a fact.** The API exposes no Shorts flag, so it goes on duration (≤60s, `bp_yt_shorts_seconds` filters it). Videos of unknown length — anything from the RSS fallback — stay in the main grid.

**`schema="true"` (default) emits an `ItemList` of `VideoObject`s using the real YouTube titles, descriptions, upload dates and durations.** This is the part no feed plugin does: they render client-side, so Google sees an empty div where the channel should be.

### [expire] — Date-gated content
```
[expire start="2025-01-01" end="2025-12-31"]content[/expire]
```

### [restrict] — Role-gated content
```
[restrict max="administrator" min="none"]content[/restrict]
```
