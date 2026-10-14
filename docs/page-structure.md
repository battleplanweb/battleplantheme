# Battle Plan Theme — WordPress page structure + image conventions

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

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

### ⚠️ A full-bleed section cannot live in the WordPress editor

`#main-content` is *always* placed inside a width-constrained grid column — it is the column that holds the sidebar, so `content-edge` / `content-full` / `content-stretch` only choose *which* constrained column it gets. A `[section width="edge"]` pasted into the editor therefore reaches the edges of `#main-content`, not the screen.

Page Top and Page Bottom render into `#wrapper-top` / `#wrapper-bottom`, which are plain `grid-template-columns: 1fr` wrappers, so a `[section]` there gets the full track set and can go truly full-bleed.

**So: any home page with edge-to-edge bands (a split hero, a full-width colour band, a wave transition) puts those sections in Page Top / Page Bottom and leaves the editor empty.** Body-copy pages with a sidebar keep using the editor as normal.

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

#### NEVER wrap the logo in an `<a href="/">` — the framework already links it

`linkHome('.logo')` in [js/script-pages.js](js/script-pages.js) runs on every page and turns
**any element carrying `class="logo"`** into the home link. It sets `tabindex="0"`,
`role="button"`, `aria-label="Return to Home Page"` and `cursor: pointer`, wires click to `/`,
maps the Enter key to that click (`keyPress()`), and marks every `<img>` inside
`aria-hidden="true"` so screen readers announce the wrapper once instead of the image too.

So the `[col class="logo"]` holds a **bare `<img>`**:

```
[col class="logo"]
 <img src="/wp-content/uploads/logo.webp" alt="Business Name — City, ST" class="logo-top" width="600" height="194" style="aspect-ratio:600/194" />
[/col]
```

Adding your own anchor is not merely redundant — it puts a real `<a>` **inside** an element
that is already `role="button"`, which is a nested interactive control: two tab stops for one
target, and an accessibility failure. Delete the anchor, keep the `class="logo"`.

Two things that follow from it:
- **It is the `.logo` class that does this, not the header.** Put `class="logo"` on anything
  (a footer lockup, for instance) and it becomes a home link too — which is fine when intended
  and surprising when it isn't. A footer logo that should NOT be clickable simply doesn't get
  the class.
- **It is JavaScript, so the logo isn't a link with JS off** and carries no `href` for crawlers.
  That's the framework's deliberate trade; don't "fix" it by adding the anchor back.

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

### Archive images — the first one loads eagerly (don't "fix" this back)

`[build-archive]` gives the **first archive image rendered on a page** `loading="eager"` + `fetchpriority="high"`; every one after it stays lazy. WordPress core's LCP heuristic spends its "don't lazy-load this one" budget on the **site logo** (the first image in the document), which leaves the actual largest above-the-fold image — the first archive card — both lazy *and* unprioritised. On a mobile archive that image is the LCP, so it was being deferred on every archive page fleet-wide.

The counter (`$bpArchiveImgCount` in [functions-shortcodes.php](functions-shortcodes.php)) is a request-scoped `static`, deliberately **page-scoped rather than per-archive** — exactly one image should ever claim `fetchpriority="high"`, even when several archive blocks render on the same page. Only the image-producing branches increment it, so a text-only card can't consume the slot.

Applies to every archive: posts, galleries, testimonials, products, jobsite_geo.

### Mockup placeholder

When building mockup/draft markup before the real images are picked, point **every** `<img src="…">` (the logo excepted) at one of these placeholders, chosen by the image's aspect ratio:

```
https://bp-webdev.com/wp-content/uploads/x-square.webp   ← square images (1:1)
https://bp-webdev.com/wp-content/uploads/x-horz.webp     ← landscape / wider-than-tall
https://bp-webdev.com/wp-content/uploads/x-vert.webp     ← portrait / taller-than-wide
```

Pick the one that matches the `width`/`height` (and `aspect-ratio`) you set on the tag: square → `x-square`, landscape → `x-horz`, portrait → `x-vert`. The **logo is the only exception** — use its real filename, never a placeholder.

Glendon swaps to the real image filenames once the design is locked. Don't invent fake filenames like `hero-tech-800x600.webp` — use the matching placeholder URL until told otherwise.
