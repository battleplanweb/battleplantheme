# CSS Inventory — Battle Plan framework theme

Generated 2026-11-19 with the `theme-css-system` skill. It scans all 39 `style*.css` files in the theme root: 28,765 lines and about 5,000 rule blocks.

How to use this file:

- **Before writing CSS,** look up the token or component you need in Parts 1 and 2. Write a new rule only when nothing here fits.
- **Part 3 is the work list.** It holds the ten consolidations that pay off most, largest first.
- **Regenerating it:** the counts come from a postcss scan. "Uses" means `var(--x)` references across the framework's CSS, PHP and JS. Client child themes (`battleplantheme-site/style-site.css`, 185 of them) were checked separately where noted.

---

## Progress

### Done 2026-11-19

Undeployed. The counts elsewhere in this file are from **before** this work.

- **Settings that did nothing, now wired up:**
  - `--input-outline` / `--input-outline-focus` now reach every text input, select and textarea (style-forms.css, and the WooCommerce coupon field).
  - `--button-transition` / `--button-transition-hover` now reach the framework button rule and the `.icon-btn` background.
  - Sites that set a non-default value will see it take effect:
    - Dutton HVAC and Jimmy Carver's get the outline on their form fields.
    - Bounce Bungee, Commercial Air Services, Lovejoy and Empire get their button transitions.
- **`--label-background` retired, not wired.** The resting label sits inside the field, so it has no background of its own. The lines in 27 child themes are harmless leftovers.
- **Dead CSS deleted:**
  - every admin-only rule in style-jobsite_geo.css (520 → 275 lines)
  - 18 unused tokens
  - the dead `--sp-radius` calc trio and the duplicate `--sp-radius-pill` in Site Pulse
- **Site Pulse layout copies swapped onto the design system,** and their rules deleted:
  - `.sp-mileage-quick`, `.sp-pa-inner`, `.sp-forms-cat-row`, `.sp-forms-move-wrap` and `.sp-book-template-head` now use `.sp-toolbar`
  - `.sp-sms-quiet-times` now uses `.sp-form-row`
  - `.sp-attach-modal-head` was redundant with `.sp-header`
- **Design-system fix:** `.sp-toolbar .sp-btn { width: auto }` was squashing every icon button inside a toolbar. It now skips `.sp-icon-btn` / `.sp-btn--icon`.
- **One radius/spacing scale:** `--sp-radius-sm/md/lg/xl/pill`, `--sp-card-padding`, `--store-radius`, `--store-gap`, `--pp-radius`, `--pp-gap` and `--container-radius` (Schedules) all point at style.css `--radius-*` / `--space-*`. Two values moved: `--sp-radius-xl` 22 → 24px and `--container-radius` 10 → 12px.
- **Exact duplicates merged:** `.sp-mobile-powered` into `.sp-sidebar-powered`, `.sp-fleet-name` into `.sp-review-author`, and `.schedule-shift-badge` into the shift-pill rule.
- **style-schedules.css snap pass:**
  - 330 px spacing values became `--space-*`, and 13 radii became `--radius-*` (nearest step, ties go to the smaller one; under 4px left alone).
  - 69 numeric weights became normal/bold.
  - em values were left alone: their px size depends on context.

### Found while doing it (changes items #1, #4 and #8 below)

- **Home Base is a retired module.** Customer Connect replaced it, and nothing loads `includes-home-base.php`. So style-home-base.css and style-bp-admin.css, which only the Home Base admin used, are dead files. Items #1 and #8 become "delete the retired module" rather than "merge".
- **#4 (neutral palettes) is mostly a non-issue:**
  - Site Pulse's neutrals are mixed from each client's brand colours on purpose.
  - Store, PayPal and the chat widget already use the framework grey ramp.
  - The only real copy was Home Base, which is dead.

### Done 2026-11-19, second pass

- **Home Base module deleted** (13 files: 5 includes, 2 page templates, 4 JS, style-home-base.css, style-bp-admin.css). Nothing loaded it. Items #1 and #8 are closed. Not to be confused with the Site Pulse **mileage** Home Base (a driver's store or home location, in includes-site-pulse.php), which is live and untouched.
- **Snap passes (#5):**
  - style-store.css: 128 spacing values to `--space-*`, 10 radii to `--radius-*`, 13 weights to normal/bold. Front end only; Site Pulse doesn't load it.
  - style-admin.css: the same snap, but written as **raw px on the scale**, because wp-admin doesn't load style.css and a `var(--space-*)` there resolves to nothing. 98 spacing values, 10 radii, 26 weights.
  - style-customer-connect.css: weights only (15). Its em sizes are deliberately relative to each site's body font, and the app can't be screenshot-tested in the sandbox, so they're unchanged.
- **Site Pulse look-alikes:**
  - New DS modifier `.sp-toolbar--nowrap`. `.sp-msg-addrow` and `.sp-template-head-actions` now use `sp-toolbar sp-toolbar--nowrap`; their rules are gone.
  - `.sp-card__head` (unused) and `.sp-survey-rate-row` grouped into the `.sp-page-head__row` rule.
  - `.sp-dir-searchrow` was defined twice, the second silently overriding the first; now one rule.
- **Exact duplicates merged (#7, #10 and others)**, only where the rules play the same role: Site Pulse 11 groups (tone chips, rate fills, collapsible nav, media fill, `.sp-actions`/`.sp-form-row`…), Schedules 9 (two rules written twice, the shift colours ×4 systems, actlog pills, the duty/modal heading), store 4, admin 4. Coincidental matches (unrelated things that share a colour and size) were left alone on purpose.
- **Child-theme scan (170 sites):** nothing should become a new framework default. The repeats are either (a) lines that restate the framework default (22 declarations, e.g. `.main-navigation a { background: transparent }` on 94 sites), harmless dead weight, or (b) real per-site choices split roughly 2:1. The pattern came from the starter, which wrote a plain `h1…h6` rule instead of setting the `--h-*` knobs; the starter (`theme/battleplantheme-site/style-site.css`) now sets the knobs, and its retired `--label-background` lines are gone.

- **Options and sections (Glendon's rules, 2026-11-19):**
  - New DS component `.sp-options` (stacked choices) + `.sp-options--inline` (side by side); each choice is `.sp-check-row`. Every radio/checkbox group moved onto it. `.sp-toolbar--loose` and `.sp-reminder-toggle` are deleted, along with their inline `display:block; margin…` patches.
  - The product editor uses `sp-section sp-section-store`, with titles on `.sp-section__title`. `.sp-section` gained `grid-template-columns: minmax(0, 1fr)` and zero margins on its form groups. Only the store's divider stays in `.sp-section-store`.
  - Cards that relied on inline margins (dashboard news feed, Tolls) now space their children with `.sp-stack`.
  - Verified with an A/B across all 73 panels: the only differences are these intended ones plus clock and data changes.

### Base + module conversion (started 2026-11-20)

Every class becomes `sp-<base> sp-<base>-<module>`, with CSS condensed into the base. Tracked module by module.
- **Step 1, the review family:**
  - The review, comment and email cards are now `sp-card sp-card-review|-survey|-email`.
  - New base parts: `.sp-prose`, `.sp-quote`, `.sp-rating-stars`, `.sp-source-icon`, `.sp-avatar.is-blank`, `.sp-card.is-warning`, `.sp-chip[role=button]`, `.sp-chip__count`, `.sp-stack--center`.
  - Base `.sp-card` is now grid, gap 12, `align-content: start`.
  - The toolbar margin experiment was removed.
- **Step 2, feedback stats and charts (Reviews + Comment cards):**
  - Stat tiles are `sp-card sp-card--stat` in `sp-grid sp-grid--stats sp-grid-review|-survey`. The figure is `sp-stat sp-stat--start` (+ new `__sub`, `__unit`). Clickable/active: `.sp-card.is-clickable` / `.is-active`.
  - Charts moved into DS section 9: `.sp-dist` (bars, `__row/__label(--wide)/__track/__fill/__pct`) and `.sp-pie` (`__svg/__slice/__legend/__row/__dot/__label/__pct`). Tones are `.sp-rate-good|ok|bad|neutral` (the `sp-rev-fill-*` classes are gone).
  - New base `.sp-pairs` (label + value lines; `--inline` "Label: value", `--columns` two newspaper columns). Comment-card ratings are `sp-pairs sp-pairs-survey`, extras are `sp-pairs--inline`, the edit modal's pickers are `sp-pairs--columns`. The rating circle is `sp-count sp-count--score`.
  - Also new: `.sp-stack--xs`. The survey list is `sp-stack sp-stack-survey`.
  - CSS −73 lines. A/B: the stat tiles are 3px taller (base 28px value); the charts and comment-card parts were checked with sample markup (the sandbox has no review data).
- **Icon buttons (2026-11-21):**
  - `.sp-icon-btn` (the 36px rounded square) is merged into the base `.sp-btn--icon` (round). Small icon buttons are `sp-btn--icon sp-btn--sm`, and `.sp-btn--icon.sp-btn--sm` keeps them round (previously `--sm`'s radius turned them square).
  - Every trash button is the same red-outlined circle via `.sp-btn.sp-icon-delete`, in a list row, a table, or a Save/Cancel row.
  - 54 uses renamed in the JS and 2 in the PHP.

### Still open

- **#3** Buttons. Store buttons have their own colour settings (Settings → Store), so merging them into `.button` is a product decision, not a cleanup.
- **em values** in Schedules and Customer Connect: converting needs a decision on the base size, and Customer Connect needs a real customer login to test.
- Customer Connect's own exact duplicates (5 groups), held back for the same testing reason.
- The store's two product-image rules (`.store-card-img` / `.store-slide img`) are identical, but another session was editing them, so they weren't merged.
- **Module-only classes:** 115 module classes in Site Pulse rename a component (`-card`, `-title`, `-row`, `-head`, `-actions`, `-label`, `-meta`…) without carrying the generic class. Each should become `sp-<component> sp-<component>-<module>` or be deleted. A judgement call per class, to be done module by module with A/B.
- Optional fleet cleanup: strip the 22 redundant declarations from existing child themes. Zero visual change, but it touches about 100 site repos.

---

## How the CSS is loaded (context for everything below)

| Bundle | Files | Where |
|---|---|---|
| **Critical**: inlined in `<head>` and compiled to `dist/core-critical.css` | `style-normalize.css`, `style.css`, `style-grid.css`, `style-navigation.css` | every front-end page |
| **Deferred**: compiled to `dist/core-deferred.css` | `style-testimonials.css`, `style-forms.css`, plus by module: `style-events.css`, `style-timeline.css`, `style-woocommerce.css`, `style-site-pulse.css`, `style-cue.css`, `style-holiday.css` (Dec 1–29) | front end |
| Loaded on their own pages | `style-schedules.css` (CCSO; also re-scoped into the Site Pulse panel), `style-store.css`, `style-home-base.css`, `style-customer-connect.css`, `style-ai-chat.css`, `style-jobsite_geo.css` (archive page, inlined) | per module |
| wp-admin | `style-admin.css`, `style-bp-admin.css` (Home Base admin), `style-jobsite-geo-admin.css`, `style-login.css` | admin / login |

The build is set in `functions-style-sheets.php` → `bp_get_css_sources()`.

### File sizes and token compliance

The off-scale and em counts cover padding, margin, gap, border-radius and font-size only.

| File | Lines | px on scale | px off scale | em | Numeric weights | `!important` | Raw colours |
|---|---|---|---|---|---|---|---|
| style-site-pulse.css | 6,269 | 68 | 63 | 0 | 0 | 33 | 18 |
| style-schedules.css | 4,523 | 235 | **150** | **168** | **70** | 51 | **136** |
| style-grid.css | 2,067 | 7 | 13 | 23 | 0 | 42 | 0 |
| style.css | 1,692 | 10 | 24 | 18 | 1 | 57 | 4 |
| style-admin.css | 1,680 | 62 | **112** | 5 | 26 | 34 | **155** |
| style-store.css | 1,627 | 103 | 78 | 1 | 13 | 2 | 0 |
| style-navigation.css | 1,159 | 14 | 25 | 2 | 0 | 10 | 0 |
| style-woocommerce.css | 997 | 7 | 12 | 11 | 0 | 18 | 2 |
| style-forms.css | 813 | 5 | 12 | 15 | 0 | 14 | 0 |
| style-customer-connect.css | 425 | 2 | 0 | **152** | 15 | 3 | 38 |
| style-home-base.css | 357 | 2 | 0 | **136** | 13 | 1 | 39 |
| style-ai-chat.css | 349 | 1 | 5 | 46 | 4 | 0 | 0 |
| style-bp-admin.css | 427 | 27 | 30 | 5 | 9 | 0 | 5 |
| *(26 smaller files)* | 4,380 | — | — | — | — | — | — |

"On scale" means a value in the framework scale: 0, 1, 2, 4, 8, 12, 16, 20, 24, 32, 48 or 64px.

---

## Part 1 — `:root` custom properties

There are **499 custom properties**, defined in 17 files. The full table, with value, use count and every file that redefines it, is in the [Appendix](#appendix--every-root-custom-property). Here they are by family:

### Framework (style.css): the public theming API that client child themes override

| Family | Tokens | Notes |
|---|---|---|
| Fonts | `--font-primary`, `--font-secondary`, `--font-tertiary`, `--font-text` | `--font-tertiary` is set by 87 child themes but never read by the framework |
| Grey ramp | `--white`, `--lightest-grey`, `--light-grey`, `--medium-grey`, `--dark-grey`, `--darkest-grey`, `--black` | the main neutrals, used about 370 times |
| Alpha ramps | `--white-lowest … --white-highest`, `--black-lowest … --black-highest` (7 steps each) | `-med-low` / `-med-high` are almost unused |
| Brand / text | `--accent-color`, `--accent-border`, `--accent-background`, `--warning`, `--text-color`, `--link-*` (6), `--anon-icon*`, `--mobile-menu-icons` | |
| Type scale | `--font-base-1…5` (%), `--font-size-h1…h6` (rem, h1 = 48rem stepping down), `--font-size-title` (32px), `--font-size-text` (18px), `--font-size-big` (132%), `--font-size-small` (77%), `--font-size-icon` (40px) | |
| Headings | private `--h-*` props on `h1–h6` (family, weight, size, color, transform, margin, border…) | a per-heading override API, at style.css ~L361 |
| Inputs | `--input-*` (31 tokens: color, background, padding, font, four borders, outline, shadow, radius, filter, transform, plus a `-focus` copy of each) and `--label-*` (4) | |
| Buttons | `--button-*` (52 tokens: the same shape as inputs, plus a `-hover` copy of each) | |
| Break-out box | `--break-out-*` (15) | |
| Locked / modal / close | `--locked-*`, `--modal-*`, `--close-btn-*` (12) | |
| Rhythm | `--measure-small` (1em), `--measure-base` (1.5em), `--measure-large` (2.5em), `--bullet-spacing` | **the spacing the framework actually uses**: 172 uses, and 64 child themes |
| Motion | `--main-trans-speed` (0.2s), `--main-trans-easing` (ease) | |
| **Spacing scale** | `--space-1…9` (4, 8, 12, 16, 20, 24, 32, 48, 64px) | **0 uses** in the framework and in all 185 child themes |
| **Radius scale** | `--radius-xs/sm/md/lg/xl/pill/circle` (4, 8, 12, 16, 24, 999px, 50%) | **0 uses** anywhere |
| Layout | `--sidebar-width` (356px), `--fit-min` (260px) | |
| Third-party | `--google-*` (8), `--paypal-yellow` | |

### Site Pulse (style-site-pulse.css): the one finished design system

| Family | Tokens |
|---|---|
| Neutrals | `--sp-surface`, `--sp-canvas`, `--sp-well`, `--sp-hover`, `--sp-ink`, `--sp-ink-2`, `--sp-ink-3`, `--sp-line`, `--sp-line-strong` |
| Brand / mark | `--sp-brand`, `-ink`, `-soft`, `-hover`, `-tint`, `-muted`; `--sp-mark`, `-ink`, `-soft`, `-line`, `-strong`, `-hover`; `--sp-table-head(-ink)` |
| Status | `--sp-success/-ink/-soft/-light`, `--sp-warning/…`, `--sp-danger/…`, `--sp-trend-pos/neutral/neg` |
| Type | `--sp-font`, `--sp-text-xs/sm/base/md/lg/xl` (fluid clamp, e.g. base = 15→19px), `--sp-title`, `--sp-display` |
| Space | `--sp-space-1…8` (fluid clamp from 4/8/12/16/20/24/32/48px, the same base as `--space-*`) |
| Radius | `--sp-radius-sm/md/lg/xl/pill` (8/12/16/22/999px) |
| Elevation / motion / z | `--sp-shadow-1/2`, `--sp-sheen`, `--sp-shade`, `--sp-drop(-soft)`, `--sp-focus`, `--sp-ease`, `--sp-z-bar/scrim/sheet/toast` |
| Sizes | `--sp-control-h` (48px), `--sp-bar-h`, `--sp-drawer-w`, `--sp-sidebar-width`, `--sp-card-padding`, `--sp-checkbox-size` |
| Legacy, still defined | `--sp-primary-*`, `--sp-secondary-*`, `--sp-accent-*` (21), `--sp-text`, `--sp-text-secondary/light`, `--font-size-xs…xl`, `--sp-radius` (10px) |

### Module token sets, each a private copy of the same roles

| File | Prefix | Count | Roles it re-invents |
|---|---|---|---|
| style-schedules.css | `--color-*`, `--s-color-1…8(-light)`, `--shift-a…d(-light)`, `--btn-primary/secondary/danger-*`, `--container-radius/shadow` | 54 | brand, button variants, radius, shadow. Its `:root` also **rewrites** the framework `--font-*`, `--input-*` and `--button-*` tokens |
| style-store.css | `--store-*`, `--product-img-*`, `--variation-tile-*` | 21 | gap, radius, border, muted text, button colours |
| style-customer-connect.css | `--cc-*` | 17 | font, brand, bg, surface, ink, ink-soft, line, radius, shadow, accent |
| style-home-base.css | `--hb-*` | 10 | bg, surface, ink, ink-soft, line, radius, shadow, accent (the same list as `--cc-*`) |
| style-ai-chat.css | `--chatbot-*` | 25 | launcher button, header, bubbles, radius, shadow, muted |
| style-events.css (+ jobsite files) | `--calendar-*` | 20 | |
| style-timeline.css | `--timeline-*` | 16 | |
| style-paypal.css | `--pp-*` | 9 | gap, radius, border, label/text colour, error/ok |
| style-bp-admin.css | `--sp-*` (copy) | 46 shared + 15 own | a second copy of the legacy Site Pulse tokens for wp-admin |
| style-admin.css | `--main-blue`, `--pale-*`, `--inline-edit`, plus `--an-*` / `--k-*` scoped on `.bp-analytics` | 6 + ~30 | its own ink, muted and surface for the analytics page |

### Tokens nobody reads

These have no `var()` reference in the framework.

- **Safe to delete** (also unused in every child theme):
  - `--space-1…9` and `--radius-*` (16 tokens)
  - `--sp-radius-circle`, `--sp-priority-high/medium/low`, `--sp-shadow-lg`, `--sp-white`, `--sp-secondary-light`, `--sp-secondary-contrast`, `--sp-accent-hover`, `--sp-accent-glow`, `--font-size-xl`
  - `--cc-white`, `--cc-ink-faint`, `--cc-tabbar-h`
  - `--color-main-light`, `--s-color-7-light`, `--s-color-8-light`
  - `--product-img-border-active`
- **Dead knobs.** Child themes set these, but no framework rule ever reads them, so the setting does nothing:
  - `--label-background` (27 sites)
  - `--input-outline-focus` (22 sites)
  - `--button-transition-hover` (21 sites)
  - `--calendar-background-empty` and `--calendar-day-today-color` (3 sites each)

  Either wire each one into its rule or tell the sites to drop it.
- **Unused by the framework, but used by a handful of child themes. Keep:** `--font-tertiary`, `--link-decoration`, `--link-hover-decoration`, `--button-border-top/right/bottom/left`, `--button-font-*-hover`, `--white-med-low/high`, `--black-med-low`, `--google-guaranteed-green`.

---

## Part 2 — Core reusable component classes

### A. Front-end framework (client websites)

| Kind | Classes | Driven by | File |
|---|---|---|---|
| **Typography** | `h1–h6` (each one configurable through private `--h-*` props), `:is(h1…h6) > span` sub-lines, `.fancy-list`, `.bullet-list`, `.bullet`, `.phone-link`, `.widget-title` | `--font-*`, `--font-size-h*`, `--h-*` | style.css ~L361–440 |
| **Page grid** | `section.section` (+ `.section-lock`, `.section-inline`, `.section-parallax`), named columns (`edge`, `full`, `stretch`, `default`), `#wrapper-content`, `.menu-strip` | grid template | style-grid.css L1–40 |
| **Column layouts** | `div.flex` + `.grid-1-1`, `-1-2`, `-2-1`, `-1-3`, `-3-1`, `-2-3`, `-3-2`, `-1-1-1`, `-1-2-1`, `-2-1-1`, `-1-1-2`, `-1-3-1`, `-3e … -8e`, `.grid-custom`; `.col` / `.col-inner`; collapse with `.break-none/1/2/3/4`; `div.grid-fit` | `--fit-min` / `--fit-max` | style-grid.css |
| **Blocks** | `.block`, `.block-image`, `.block-button`, `.block-text`, `.block-group`, `.side-by-side`, `.col-row-of-pics`, `.gallery-column-*` | | style-grid.css |
| **Hero** | `.hero-box`, `.hero-bg` (real `<img>`), `.hero-picture`, `.hero-stack-1280/1024/860/576` | `--hero-*` | style-grid.css ~L60–210 |
| **Sidebar / widgets** | `.content-sidebar-box`, `.sidebar-left/right`, `.sidebar-box`, `.widget`, `.widget-box`, `.widget-area` | `--sidebar-width` | style-grid.css |
| **Buttons** | `a.button`, `button:not(.unique)`, `input[type=button/reset/submit]`, `.file-upload-btn`, `ul.tabs li`, `a.page-numbers` (one rule); `.row-of-buttons`, `.block-button`; `.fancy`, `.fancy-2`, `.fancy-3`; `.icon-btn`, `.social-btn`, `.call-btn`, `.mm-bar-btn` | `--button-*` | style-forms.css ~L286, style-fancy-btn.css, style.css, style-navigation.css |
| **Opt-out** | `class="unique"` keeps a `<button>` out of the button rule | | style-forms.css |
| **Forms** | `.bp-form`, `.bp-step`, `.form-input`, `.bp-control-wrap`, `.bp-control`, `.form-label-outside`, `.width-none` / `.width-full`, `.form-no-icons`, `.bp-list` / `.bp-list-item`, `.bp-checkbox`, `.bp-acceptance`, `.bp-submit`, `.bp-response`, `.bp-spinner`, `.bp-not-valid-tip`; bare `input` / `textarea` / `select` | `--input-*`, `--label-*`, `--measure-*` | style-forms.css |
| **Card-like boxes** | `.icon-card`, `.coupon` / `.coupon-inner`, `.wp-caption`, break-out box, `.testimonials`, `.currently-open-banner` | `--break-out-*`, `--locked-*`, `--modal-*` | style.css, style-testimonials.css |
| **Navigation** | `.main-navigation`, `.widget-navigation`, `.sub-menu`, `#magic-line`, `.menu-with-logo`, `.split-menu`, mobile menu (`.mm-bar-*`, `.top-push/slide/drop`, `.side-push/slide`), `.pagination` / `.nav-links`, `.scroll-top` | | style-navigation.css |
| **Utilities** | `.align-left/right`, `.hide-mobile` / `.hide-desktop`, `.screen-480 … -1280`, `.size-half-f/s`, `.size-third-*`, `.size-quarter-*`, `.thumb-left/right/only`, `.inset-shadow`, `.tab-focus` (keyboard focus, set by JS) | | style.css |

### B. Site Pulse design system (style-site-pulse.css, "Design system" section)

Every screen in the app is built from these. Module sections only arrange them.

| Kind | Classes |
|---|---|
| Page | `.sp-page`, `.sp-page-head` / `__row`, page title / eyebrow, `.sp-section`, `.sp-split`, `.sp-view-toggle` |
| Layout | `.sp-stack` (`--sm`, `--lg`), `.sp-grid` (`--wide`), `.sp-toolbar` (`--end`), `.sp-actions` |
| Buttons | `.sp-btn` + `--primary`, `--accent`, `--inverse`, `--ghost`, `--danger`, `--sm`, `--icon`, `--block`, `.is-busy`; `.sp-icon-btn`; `.sp-thumb-x` |
| Cards | `.sp-card` + `--flush`, `--tint`, `--inset`, `--well`; `.sp-card__head`, `.sp-card__eyebrow` |
| Lists | `.sp-list` (`.is-split`), `.sp-row` (`__main`, `__title`, `__value`, `.is-unread`, `.is-muted`), `.sp-meta`, groups (`<details>`) |
| Pills | `.sp-chip` (+ tones), `.sp-count` (`--quiet`), `.sp-status-badge` → chip alias, `.sp-filter` (`.is-active`) |
| Fields | `.sp-field`, `.sp-field__label`, `.sp-field__hint` (= `.sp-help-text`), `.sp-flag--danger/--success`, `.sp-input`, `.sp-select`, `.sp-textarea`, `.sp-control`, `.sp-combo-input`, `.sp-input--locked`, `.sp-check-row` (every checkbox and radio line), `.sp-stars` |
| Feedback | `.sp-notice` (+ `--block`, `--warning`, `--danger`, `--success`), `.sp-empty`, `.sp-skeleton`, `.sp-loading` |
| Data | `.sp-table` (`th.is-num`), `.sp-table-wrap` |
| Overlays | `.sp-modal` (`-wide`), sheets, confirm sheet, toasts |
| People | `.sp-avatar` (`--xl`, initials fallback), `.sp-datebadge` |

### C. The other apps, each with its own mini-system

| App | Buttons | Cards / boxes | Fields | Pills |
|---|---|---|---|---|
| Schedules (CCSO) | `.schedules-btn` (`-primary`, `-danger`, `-small`) | `.schedules-modal-box`, `.toolbar` | `.form-group`, `.form-group-row/full` | `.shift-pill`, `.priority-pill`, `.user-shift-badge`, `.schedule-shift-badge`, `.cover-shift-badge` |
| Store | `.store-btn` (`-secondary`), `.store-buy-pill` | `.store-card`, `.store-account-card`, `.store-tile` | `.store-field`, `.store-field-row` | `.store-card-badge` |
| Home Base | `.hb-btn` (`-primary`, `-sm`), `.hb-link` | `.hb-card`, `.hb-row` | `.hb-field` | `.hb-chip`, `.hb-badge` |
| Customer Connect | `.cc-btn` (`-primary`, `-sm`, `-lg`) | `.cc-card`, `.cc-row` | `.cc-field` | `.cc-chip`, `.cc-badge` |
| wp-admin analytics | `.bp-an-mbtn`, `-gbtn`, `-rbtn`, `-sbtn`, `-pbtn` | `.bp-an-card`, `.bp-an-tile` | — | `.bp-an-delta` |
| Home Base admin (bp-admin) | `.sp-btn` (+ legacy `-primary`, `-secondary`, `-ghost`, `-delete`, `-sm`, `-full`), `.sp-topbar-btn` | `.sp-card`, `.sp-card-sm`, `.sp-tile` | `.sp-form-group` | `.sp-nav-badge` |

---

## Part 3 — Top 10 duplications to consolidate

These are ranked by how many rules each one removes, and how many places a change currently has to be made.

### 1. Customer Connect and Home Base are the same stylesheet twice

- **Evidence:**
  - Rename `cc-` ↔ `hb-` and **106 of 111 Home Base selectors exist in Customer Connect, 98 with identical declarations.**
  - Examples: `.cc-badge` = `.hb-badge` (13 declarations), `.cc-cust-opt` = `.hb-cust-opt`, and `.cc-quick`, `.cc-eq-list` and `.cc-cust-list` are all copies of `.hb-*`.
  - Between them the two files hold 288 em values and 77 raw colours, mostly the same ones.
- **Consolidate:** one shared customer-PWA stylesheet with neutral class names (say `.cp-*`), themed by a single token set. Each app keeps only its few unique rules.
- **Payoff:** about 300 lines removed, and the two PWAs can no longer drift apart.

### 2. Six spacing scales and seven radius scales, and the framework's own scale is unused

- **Evidence:**
  - Spacing comes from `--space-1…9` (0 uses), `--sp-space-1…8`, `--measure-*` (em), `--store-gap`, `--pp-gap`, `--sp-card-padding`, `--card-padding`, `--chatbot-form-padding`, and raw px.
  - Radius comes from `--radius-*` (0 uses), `--sp-radius-*`, `--store-radius` (8px), `--hb-radius`, `--cc-radius`, `--pp-radius`, `--container-radius`, `--chatbot-radius`, `--input-border-radius` and `--button-border-radius`.
  - `--sp-radius-sm` / `-lg` are defined **twice** in style-site-pulse.css: as `calc(10px × 0.8 / 1.6)` at L43–44, then again as 8px / 16px at L158–160. style-bp-admin.css repeats the calc version.
- **Consolidate:** make `--space-*` and `--radius-*` in style.css the only scale. Point `--sp-space-*` / `--sp-radius-*` and every module token (`--store-radius: var(--radius-sm)`, and so on) at them, then delete the legacy `--sp-radius` calc trio.
- **Payoff:** one place to retune spacing and rounding across every app, and around 40 tokens retired.

### 3. Seven separate button systems

- **Evidence:**
  - Framework: `button`, `.button` and `--button-*` (style-forms.css L286).
  - `.sp-btn` (Site Pulse L447).
  - `.schedules-btn` + `--btn-primary/secondary/danger-*` (schedules L740).
  - `.store-btn` / `.store-btn-secondary` (store L953).
  - `.hb-btn` (home-base L155) and `.cc-btn` (customer-connect L184).
  - `.bp-an-*btn` (admin L931).
  - bp-admin's legacy `.sp-btn-*` (bp-admin L253).
  - Each one re-declares padding, radius, disabled state and hover from scratch. Disabled alone is written five ways: opacity .4 / .45 / .5 / .55 / .6.
- **Consolidate:**
  - **Front end:** Store, Home Base and Customer Connect use the framework `.button` (it already takes the client's palette through `--button-*`). A variant goes through a modifier, not a new system.
  - **Apps:** Schedules and bp-admin use `.sp-btn` and its modifiers.
  - Shared states: one `:disabled` / `.is-disabled` rule per system.
- **Payoff:** about 150 lines removed, and a button looks the same in every app.

### 4. One neutral palette, re-invented in every module

- **Evidence:**
  - "Ink / soft ink / line / surface / background" is defined six times: `--sp-ink/-2/-3`, `--sp-line`, `--sp-surface`; `--hb-ink/-soft`, `--hb-line`, `--hb-surface`, `--hb-bg`; `--cc-ink/-soft/-faint`, `--cc-line`, `--cc-surface`, `--cc-bg`; `--an-ink/ink2/muted/surface` (admin); `--store-muted`; `--pp-text-color/label-color`; `--chatbot-muted`.
  - Raw greys sit next to them: `#eef1f4`, `#eaecf0`, `#94a3b8`, `#898781`, `rgba(0,0,0,.06/.08)` in four files.
- **Consolidate:** add a semantic neutral set to style.css (`--ink`, `--ink-2`, `--ink-3`, `--line`, `--surface`, `--canvas`) mapped onto the existing grey ramp. Module tokens become aliases (`--hb-ink: var(--ink)`), or are dropped.
- **Payoff:** a client palette or dark-mode change reaches every module, and about 30 tokens plus about 60 raw colours are retired.

### 5. Off-scale pixel values: the "8px vs 9px" problem

- **Evidence:** 887 raw px values in padding/margin and 214 in gap. The most common off-scale ones sit one or two pixels from a scale step:

  | Value | Count | Snap to |
  |---|---|---|
  | `10px` | 113 padding/margin + 26 gap | 8 or 12 |
  | `6px` | 61 + 31 gap | 4 or 8 |
  | `14px` | 55 + 8 gap | 12 or 16 |
  | `5px` | 40 + 8 gap | 4 |
  | `3px` | 28 + 7 gap | 4 (or 2) |
  | `15px` / `18px` / `7px` / `9px` / `13px` / `11px` | 20 / 12 / 10 / 6 / 6 / 4 | nearest step |

  - **Font sizes:** 28 different px values, including 9, 10, 11, 13, 15, 17, 19, 21 and 23.
  - **Border radii:** 14 different values (2, 3, 4, 5, 6, 8, 9, 10, 12, 15, 18, 20, 24 and 999px).
  - **Worst offenders:** style-schedules.css (150 off-scale px + 168 em + 70 numeric weights), style-admin.css (112), style-store.css (78), style-site-pulse.css (63, mostly `font-size: 11px` ×9, `gap: 6px` ×7, `padding: 10px/14px` ×13).
- **Consolidate:** run the snap pass already used on Site Pulse (`phase2.js`: px → nearest token, numeric weights → normal/bold, em → px) file by file, starting with schedules, admin and store.
- **Payoff:** about 1,000 values move onto tokens, and the sizing rhythm matches across apps.

### 6. Layout one-liners rebuilt as module classes (140 rules)

- **Evidence:**
  - 140 rules contain nothing but `display: flex|grid` + direction / align / justify / wrap / gap. The common shapes:
    - `flex` + `column` + gap: 25 rules with 9 different gaps
    - plain `grid` + gap: 17
    - `flex` + `align-items: center` + gap: 15
    - `flex` + `wrap` + gap: 12
    - `flex` + `center` + `wrap`: 10
    - `flex` + `center` + `space-between`: 8
  - 64 of them are in Site Pulse. These were missed in the 2026-11-18 refactor and are **exact copies** of design-system classes:
    - `.sp-mileage-quick`, `.sp-pa-inner`, `.sp-forms-cat-row`, `.sp-forms-move-wrap`, `.sp-book-template-head` = `.sp-toolbar`
    - `.sp-form-row`, `.sp-sms-quiet-times` = `.sp-actions`
    - `.sp-attach-modal-head`, `.sp-survey-rate-row`, `.sp-page-head__row` = `.sp-card__head`
    - `.sp-msg-addrow`, `.sp-dir-searchrow`, `.sp-template-head-actions` are identical to each other
  - Outside Site Pulse:
    - `.cc-form` / `.hb-form` / `.cc-field` / `.cc-notif-list` …: 13 rules each, gaps 0.35em to 0.9em
    - `.bp-an-bars` / `.shift-card-info` / `.cover-request-list` / `.store-qty-wrap` (gaps 3, 4, 6 and 10px)
- **Consolidate:**
  - **Site Pulse:** swap those classes for the DS ones in the markup (`swapcls.js`) and delete the rules.
  - **Front end:** add the framework equivalents, `.stack` (`--sm`, `--lg`) and `.cluster` (row + wrap + center), on the `--space-*` scale.
- **Payoff:** about 120 rules gone, and every gap comes from the scale.

### 7. Pills, badges and chips: five systems

- **Evidence:**
  - Rules with the same small-caps pill shape:
    - `.sp-chip`
    - `.shift-pill` / `.priority-pill` / `.user-shift-badge` / `.user-priority` (schedules L1974), **with an identical copy** as `.schedule-shift-badge` at L3464
    - `.cover-shift-badge`
    - `.hb-chip` / `.cc-chip` (+ 4 hard-coded tones each)
    - `.store-card-badge`, `.store-buy-pill`
    - `.bp-an-delta`
  - Count bubbles: `.sp-count`, `.sp-nav-badge`, `.hb-badge`, `.cc-badge`, `.store-cart-count`.
- **Consolidate:** one pill base with tone modifiers (`--ok`, `--warn`, `--danger`, `--muted`, `--brand`), and one count bubble, per layer: `.sp-chip` / `.sp-count` in the apps, a framework `.chip` on the front end.
- **Payoff:** about 60 lines removed, and status colours agree everywhere.

### 8. style-bp-admin.css is a second, older copy of Site Pulse

- **Evidence:**
  - It re-declares 46 Site Pulse tokens.
  - 52 of its selectors also exist in style-site-pulse.css (`.sp-btn`, `.sp-card`, `.sp-nav-item`, `.sp-sidebar*`, `.sp-table`, `.sp-input`, …), and **only 10 still match**.
  - It also keeps the class names the Site Pulse refactor retired: `.sp-btn-primary`, `.sp-btn-secondary`, `.sp-btn-ghost`, `.sp-btn-full`, `.sp-card-sm`, `.sp-tile`.
  - The same class name therefore renders differently in wp-admin (Home Base admin) and in the app.
- **Consolidate:**
  - Split the Site Pulse "Design tokens + Design system" sections into one stylesheet that both the app and the Home Base admin enqueue.
  - Rename the admin markup (`includes-home-base-admin.php`) to the current DS classes.
  - Delete style-bp-admin.css except its wp-admin layout shims.
- **Payoff:** about 350 lines removed, and a single source for the app look.

### 9. Admin rules duplicated in the front-end jobsite stylesheet

- **Evidence:**
  - style-jobsite_geo.css is inlined only on the front-end archive page (`archive-jobsite_geo.php`), yet it carries **56 `body.wp-admin.post-type-jobsite_geo …` selectors**. Those can never match there.
  - 29 of them have identical bodies in style-jobsite-geo-admin.css, the file wp-admin actually loads.
  - The calendar tokens (`--calendar-*`) are also defined in three files: events, jobsite_geo and jobsite-geo-admin.
- **Consolidate:** delete the wp-admin block from style-jobsite_geo.css. Define `--calendar-*` once, in style-events.css.
- **Payoff:** about 200 dead lines out of a page-inlined file, which is a direct page-weight saving.

### 10. Muted and meta text, and heading micro-variants

- **Evidence:** the same "small grey line" or "section heading" written over and over at slightly different sizes and margins:
  - **Schedules `h2`, four versions:** `.schedules-panel-inner h2` 1.1em / margin 18px, `.schedules-my-claims h2` 1.1em / 14px, `.schedules-modal-box h2` 1.15em / 20px, `.settings-header h2` 1.1em / 8px.
  - **Admin analytics muted text, four versions:** `.bp-an-sub` 13px / ml 8px, `.bp-an-tile-unit` 11px / ml 6px, `.bp-an-period` 12px, `.bp-an-cardnote` 11px. Plus `.bp-an-techtitle` = `.bp-an-maptitle` apart from 600 vs 700 weight.
  - **Exact copies:** `.sp-sidebar-powered` = `.sp-mobile-powered`, `.sp-review-author` = `.sp-fleet-name` (bold + ellipsis), `.cc-auth-sub` ≈ `.cc-eq-meta`.
- **Consolidate:** one meta/eyebrow text class and one truncate utility per layer (Site Pulse already has `.sp-meta`, `.sp-card__eyebrow` and `.sp-row__title`). Section headings use the shared heading style and a margin from the scale.
- **Payoff:** about 40 rules removed, and text hierarchy matches across screens.

### Smaller repeats

These are worth folding into the passes above.

- **style-grid.css hero stacking:** the `.hero-stack-1280/1024/860/576` rules are four identical blocks, one per breakpoint (L126–205). One rule driven by a `--hero-stacked` flag that each media query sets would replace them.
- **style-scroller.css swipe:** `.swipe` is likewise four identical blocks per breakpoint (L32–60).
- **Media fill:** `display:block; width:100%; height:100%; object-fit:cover` is written 4 times (`.sp-avatar img`, `.sp-action-attach-thumb img`, `.store-card-img`, `.store-slide img`). It should be one `.media-fill` utility.
- **List reset:** `list-style:none; margin:0; padding:0` is written 5 times (events, posts, Site Pulse, store ×2).
- **`!important`:** 378 declarations in total, the most in style.css (57), schedules (51), style-grid.css (42), admin (34) and Site Pulse (33). Many exist only to beat the long chained selectors: woocommerce has 188 selectors four or more levels deep, navigation 53, admin 52.

---

## Suggested order

1. **Quick, safe deletions:**
   - the dead admin block in jobsite_geo (#9)
   - the zero-use tokens
   - the Site Pulse layout clones (#6), done with `swapcls.js` + `ops.js` from the 2026-11-18 refactor
2. **Tokens first (#2, #4):** one spacing/radius scale and one neutral set in style.css. Everything after this builds on them.
3. **Twin files (#1, #8):** a shared customer-PWA sheet, and a shared Site Pulse DS sheet.
4. **Components (#3, #7, #10):** buttons, pills, meta text.
5. **Snap pass (#5),** file by file, with screenshot A/B as was done for Site Pulse.

---

## Appendix — every `:root` custom property

Each token is listed under the file that owns it. "Uses" counts `var()` references across the framework's CSS, PHP and JS (**0** is bold). "Also defined in" lists the other files that redefine it, often with a different value. Values that change inside one file are shown as `first → later`.


### style.css (195)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--font-primary` | `Helvetica, Arial, Verdana, sans-serif` | 14 | admin, bp-admin, schedules, site-pulse |
| `--white` | `rgba(255, 255, 255, 1)` | 124 | admin, bp-admin |
| `--lightest-grey` | `rgba(225, 225, 225, 1)` | 33 | admin |
| `--light-grey` | `rgba(200, 200, 200, 1)` | 45 | admin |
| `--medium-grey` | `rgba(130, 130, 130, 1)` | 27 | admin |
| `--dark-grey` | `rgba(60, 60, 60, 1)` | 58 | admin |
| `--darkest-grey` | `rgba(30, 30, 30, 1)` | 9 | admin |
| `--black` | `rgba(0, 0, 0, 1)` | 70 | admin |
| `--white-lowest` | `rgba(255, 255, 255, 0.1)` | 8 | admin |
| `--white-low` | `rgba(255, 255, 255, 0.25)` | 9 | admin |
| `--white-med` | `rgba(255, 255, 255, 0.5)` | 6 | admin |
| `--white-high` | `rgba(255, 255, 255, 0.75)` | 10 | admin |
| `--white-highest` | `rgba(255, 255, 255, 0.9)` | 3 | admin |
| `--black-lowest` | `rgba(0, 0, 0, 0.1)` | 42 | admin |
| `--black-low` | `rgba(0, 0, 0, 0.25)` | 33 | admin |
| `--black-med` | `rgba(0, 0, 0, 0.5)` | 28 | admin |
| `--black-high` | `rgba(0, 0, 0, 0.75)` | 11 | admin |
| `--black-highest` | `rgba(0, 0, 0, 0.9)` | 18 | admin |
| `--font-base-5` | `6.25%` | 3 | admin |
| `--input-color` | `var(--medium-grey)` | 14 | admin, bp-admin, schedules, site-pulse |
| `--input-background` | `var(--white-highest)` | 12 | admin, bp-admin, schedules, site-pulse |
| `--input-padding` | `3px 7px` | 9 | admin, bp-admin, schedules, site-pulse |
| `--input-font-size` | `85%` | 11 | admin, bp-admin, schedules, site-pulse |
| `--input-font-weight` | `normal` | 7 | admin, schedules |
| `--input-border` | `none` | 27 | admin, bp-admin, login, schedules, site-pulse |
| `--input-border-top` | `var(--input-border)` | 3 | admin, schedules |
| `--input-border-right` | `var(--input-border)` | 3 | admin, schedules |
| `--input-border-bottom` | `var(--input-border)` | 3 | admin, schedules |
| `--input-border-left` | `var(--input-border)` | 3 | admin, schedules |
| `--input-outline` | `none` | 5 | admin, schedules |
| `--input-box-shadow` | `none` | 8 | admin, schedules |
| `--input-border-radius` | `0` | 11 | admin, bp-admin, schedules, site-pulse |
| `--input-filter` | `none` | 8 | admin, schedules |
| `--input-backdrop-filter` | `none` | 4 | admin, schedules |
| `--input-transform` | `none` | 7 | admin, schedules |
| `--input-color-focus` | `var(--input-color)` | 5 | admin, login, schedules |
| `--input-background-focus` | `var(--input-background)` | 4 | admin, schedules |
| `--input-font-size-focus` | `var(--input-font-size)` | 3 | admin, schedules |
| `--input-font-weight-focus` | `var(--input-font-weight)` | 2 | admin, schedules |
| `--input-border-focus` | `var(--input-border)` | 12 | admin, bp-admin, schedules, site-pulse |
| `--input-border-top-focus` | `var(--input-border-focus)` | 1 | admin, schedules |
| `--input-border-right-focus` | `var(--input-border-focus)` | 1 | admin, schedules |
| `--input-border-bottom-focus` | `var(--input-border-focus)` | 1 | admin, schedules |
| `--input-border-left-focus` | `var(--input-border-focus)` | 1 | admin, schedules |
| `--input-outline-focus` | `var(--input-outline)` | **0** | admin, schedules |
| `--input-box-shadow-focus` | `var(--input-box-shadow)` | 2 | admin, schedules |
| `--input-border-radius-focus` | `var(--input-border-radius)` | 4 | admin, schedules |
| `--input-filter-focus` | `var(--input-filter)` | 2 | admin, schedules |
| `--input-backdrop-filter-focus` | `none` | 2 | admin, schedules |
| `--input-transform-focus` | `var(--input-transform)` | 2 | admin, schedules |
| `--button-color` | `var(--black-med)` | 22 | admin, bp-admin, login, schedules, site-pulse |
| `--button-background` | `var(--black-low)` | 24 | admin, bp-admin, login, schedules, site-pulse |
| `--button-border` | `none` | 33 | admin, bp-admin, login, schedules, site-pulse |
| `--button-box-shadow` | `none` | 11 | admin, bp-admin, schedules, site-pulse |
| `--button-font-weight` | `normal` | 10 | admin, bp-admin, schedules, site-pulse |
| `--button-color-hover` | `var(--button-color)` | 17 | admin, bp-admin, schedules, site-pulse |
| `--button-background-hover` | `var(--button-background)` | 18 | admin, bp-admin, login, schedules, site-pulse |
| `--button-border-hover` | `var(--button-border)` | 24 | admin, bp-admin, login, schedules, site-pulse |
| `--button-box-shadow-hover` | `var(--button-box-shadow)` | 7 | admin, bp-admin, schedules, site-pulse |
| `--locked-color` | `var(--black)` | 6 | admin |
| `--locked-background` | `var(--white)` | 4 | admin |
| `--locked-border` | `none` | 4 | admin |
| `--modal-color` | `var(--locked-color)` | 1 | admin |
| `--modal-background` | `var(--locked-background)` | 1 | admin |
| `--modal-border` | `var(--locked-border)` | 1 | admin |
| `--close-btn-color` | `var(--button-color)` | 2 | admin |
| `--close-btn-background` | `var(--button-background)` | 2 | admin |
| `--close-btn-border` | `var(--button-border)` | 2 | admin |
| `--close-btn-color-hover` | `var(--button-color-hover)` | 1 | admin |
| `--close-btn-background-hover` | `var(--button-background-hover)` | 1 | admin |
| `--close-btn-border-hover` | `var(--button-border-hover)` | 1 | admin |
| `--font-text` | `"Open Sans", sans-serif` | 23 | bp-admin, schedules, site-pulse |
| `--font-size-text` | `18px` | 33 | bp-admin, site-pulse |
| `--button-font-family` | `var(--font-secondary)` | 16 | bp-admin, schedules, site-pulse |
| `--button-font-size` | `1.2em` | 18 | bp-admin, schedules, site-pulse |
| `--button-padding` | `0.3em 1em` | 15 | bp-admin, schedules, site-pulse |
| `--button-border-radius` | `0` | 12 | bp-admin, schedules, site-pulse |
| `--button-transform` | `none` | 6 | bp-admin, schedules, site-pulse |
| `--button-transform-hover` | `var(--button-transform)` | 5 | bp-admin, schedules, site-pulse |
| `--font-secondary` | `var(--font-primary)` | 7 | schedules |
| `--font-tertiary` | `var(--font-secondary)` | **0** | schedules |
| `--accent-color` | `var(--black)` | 13 | schedules |
| `--accent-border` | `1px solid var(--black)` | 1 | schedules |
| `--accent-background` | `var(--white-high)` | 6 | schedules |
| `--warning` | `rgba(255, 0, 0, 1)` | 15 | schedules |
| `--anon-icon` | `var(--accent-color)` | 5 | schedules |
| `--anon-icon-bg` | `var(--accent-background)` | 3 | schedules |
| `--text-color` | `var(--dark-grey)` | 26 | schedules |
| `--link-color` | `var(--text-color)` | 1 | schedules |
| `--link-hover-color` | `var(--text-color)` | 1 | schedules |
| `--link-bg` | `transparent` | 1 | schedules |
| `--link-hover-bg` | `transparent` | 1 | schedules |
| `--link-decoration` | `none` | **0** | schedules |
| `--link-hover-decoration` | `none` | **0** | schedules |
| `--input-text-shadow` | `none` | 6 | schedules |
| `--input-text-shadow-focus` | `var(--input-text-shadow)` | 2 | schedules |
| `--button-border-top` | `var(--button-border)` | **0** | schedules |
| `--button-border-right` | `var(--button-border)` | **0** | schedules |
| `--button-border-bottom` | `var(--button-border)` | **0** | schedules |
| `--button-border-left` | `var(--button-border)` | **0** | schedules |
| `--button-text-shadow` | `none` | 5 | schedules |
| `--button-outline` | `none` | 5 | schedules |
| `--button-filter` | `none` | 5 | schedules |
| `--button-text-transform` | `capitalize` | 6 | schedules |
| `--button-font-variant` | `none` | 3 | schedules |
| `--button-letter-spacing` | `0` | 5 | schedules |
| `--button-transition` | `all var(--main-trans-speed) var(--main-trans-easing)` | 10 | schedules |
| `--button-font-family-hover` | `var(--button-font-family)` | **0** | schedules |
| `--button-font-weight-hover` | `var(--button-font-weight)` | **0** | schedules |
| `--button-font-size-hover` | `var(--button-font-size)` | **0** | schedules |
| `--button-border-top-hover` | `var(--button-border-hover)` | 1 | schedules |
| `--button-border-right-hover` | `var(--button-border-hover)` | 1 | schedules |
| `--button-border-bottom-hover` | `var(--button-border-hover)` | 1 | schedules |
| `--button-border-left-hover` | `var(--button-border-hover)` | 1 | schedules |
| `--button-padding-hover` | `var(--button-padding)` | 3 | schedules |
| `--button-border-radius-hover` | `var(--button-border-radius)` | 5 | schedules |
| `--button-text-shadow-hover` | `var(--button-text-shadow)` | 3 | schedules |
| `--button-outline-hover` | `var(--button-outline)` | 3 | schedules |
| `--button-filter-hover` | `var(--button-filter)` | 3 | schedules |
| `--button-text-transform-hover` | `var(--button-text-transform)` | 3 | schedules |
| `--button-font-variant-hover` | `var(--button-font-variant)` | 2 | schedules |
| `--button-letter-spacing-hover` | `var(--button-letter-spacing)` | 2 | schedules |
| `--button-transition-hover` | `var(--button-transition)` | **0** | schedules |
| `--font-size-h4` | `calc(var(--font-size-h3) - 6rem)` | 6 | site-pulse |
| `--white-med-low` | `rgba(255, 255, 255, 0.37)` | **0** |  |
| `--white-med-high` | `rgba(255, 255, 255, 0.62)` | **0** |  |
| `--black-med-low` | `rgba(0, 0, 0, 0.37)` | **0** |  |
| `--black-med-high` | `rgba(0, 0, 0, 0.62)` | 1 |  |
| `--mobile-menu-icons` | `var(--white-high)` | 2 |  |
| `--font-base-4` | `calc(var(--font-base-5) * 0.80)` | 3 |  |
| `--font-base-3` | `calc(var(--font-base-4) * 1.00)` | 3 |  |
| `--font-base-2` | `calc(var(--font-base-3) * 0.95)` | 3 |  |
| `--font-base-1` | `calc(var(--font-base-2) * 0.95)` | 2 |  |
| `--font-size-h1` | `48rem` | 5 |  |
| `--font-size-h2` | `calc(var(--font-size-h1) - 6rem)` | 4 |  |
| `--font-size-h3` | `calc(var(--font-size-h2) - 6rem)` | 5 |  |
| `--font-size-h5` | `calc(var(--font-size-h4) - 6rem)` | 3 |  |
| `--font-size-h6` | `calc(var(--font-size-h5) - 3rem)` | 4 |  |
| `--font-size-title` | `32px` | 1 |  |
| `--label-color` | `var(--input-color)` | 3 |  |
| `--label-background` | `var(--input-background)` | **0** |  |
| `--label-color-focus` | `var(--input-color-focus)` | 2 |  |
| `--label-background-focus` | `var(--input-background-focus)` | 1 |  |
| `--input-padding-focus` | `var(--input-padding)` | 2 |  |
| `--contact-spinner-color` | `var(--button-background)` | 1 |  |
| `--break-out-width` | `100%` | 1 |  |
| `--break-out-max-width` | `960px` | 1 |  |
| `--break-out-background` | `var(--black-lowest)` | 1 |  |
| `--break-out-border-top` | `2px solid var(--black)` | 1 |  |
| `--break-out-border-bottom` | `2px solid var(--black)` | 1 |  |
| `--break-out-border-right` | `none` | 1 |  |
| `--break-out-border-left` | `none` | 1 |  |
| `--break-out-padding` | `1.5em 2.5em` | 1 |  |
| `--break-out-margin` | `2.5em auto` | 1 |  |
| `--break-out-box-shadow` | `none` | 1 |  |
| `--break-out-border-radius` | `0` | 1 |  |
| `--break-out-outline` | `none` | 1 |  |
| `--break-out-filter` | `none` | 1 |  |
| `--break-out-transition` | `all var(--main-trans-speed) var(--main-trans-easing)` | 1 |  |
| `--font-size-big` | `132%` | 1 |  |
| `--font-size-small` | `77%` | 4 |  |
| `--font-size-icon` | `40px` | 4 |  |
| `--main-trans-speed` | `0.2s` | 51 |  |
| `--main-trans-easing` | `ease` | 57 |  |
| `--measure-base` | `1.5em` | 79 |  |
| `--measure-small` | `1.0em` | 64 |  |
| `--measure-large` | `2.5em` | 29 |  |
| `--bullet-spacing` | `0.7em` | 1 |  |
| `--sidebar-width` | `356px` | 2 |  |
| `--space-1` | `4px` | **0** |  |
| `--space-2` | `8px` | **0** |  |
| `--space-3` | `12px` | **0** |  |
| `--space-4` | `16px` | **0** |  |
| `--space-5` | `20px` | **0** |  |
| `--space-6` | `24px` | **0** |  |
| `--space-7` | `32px` | **0** |  |
| `--space-8` | `48px` | **0** |  |
| `--space-9` | `64px` | **0** |  |
| `--radius-xs` | `4px` | **0** |  |
| `--radius-sm` | `8px` | **0** |  |
| `--radius-md` | `12px` | **0** |  |
| `--radius-lg` | `16px` | **0** |  |
| `--radius-xl` | `24px` | **0** |  |
| `--radius-pill` | `999px` | **0** |  |
| `--radius-circle` | `50%` | **0** |  |
| `--fit-min` | `260px` | 2 |  |
| `--google-guaranteed-green` | `rgba(0, 161, 79, 1)` | **0** |  |
| `--paypal-yellow` | `rgba(255, 196, 57, 1)` | 1 |  |
| `--google-review-border` | `5px solid var(--light-grey)` | 1 |  |
| `--google-review-bg` | `var(--white)` | 1 |  |
| `--google-star-color` | `rgba(251, 188, 4, 1)` | 7 |  |
| `--google-star-stroke` | `rgba(0, 0, 0, 0.3)` | 6 |  |
| `--google-star-shadow` | `rgba(70, 70, 70, 0.6)` | 2 |  |
| `--google-value-text` | `var(--text-color)` | 1 |  |
| `--google-total-text` | `var(--text-color)` | 1 |  |

### style-site-pulse.css (110)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--font-size-xs` | `calc(var(--font-size-text) * 0.72)` | 1 | bp-admin |
| `--font-size-sm` | `calc(var(--font-size-text) * 0.857)` | 5 | bp-admin |
| `--font-size-md` | `calc(var(--font-size-text) * 1.15)` | 2 | bp-admin |
| `--font-size-lg` | `calc(var(--font-size-text) * 1.29)` | 2 | bp-admin |
| `--font-size-xl` | `calc(var(--font-size-text) * 1.72)` | **0** | bp-admin |
| `--sp-checkbox-size` | `20px` | 3 | bp-admin |
| `--sp-radius` | `10px` | 6 | bp-admin |
| `--sp-radius-sm` | `calc(var(--sp-radius) * 0.8) → 8px` | 63 | bp-admin |
| `--sp-radius-lg` | `calc(var(--sp-radius) * 1.6) → 16px` | 15 | bp-admin |
| `--sp-radius-pill` | `999px` | 28 | bp-admin |
| `--sp-primary-lightest` | `#f2f3f6` | 2 | bp-admin |
| `--sp-primary-light` | `#d4d8dd` | 2 | bp-admin |
| `--sp-primary-color` | `#3e434a` | 5 | bp-admin |
| `--sp-primary-dark` | `#252930` | 5 | bp-admin |
| `--sp-primary-darkest` | `#12161c` | 5 | bp-admin |
| `--sp-primary-contrast` | `#ffffff` | 4 | bp-admin |
| `--sp-primary-line` | `rgba(255,255,255,0.10)` | 4 | bp-admin |
| `--sp-secondary-lightest` | `#dae9de` | 11 | bp-admin |
| `--sp-secondary-light` | `#cae2d0` | **0** | bp-admin |
| `--sp-secondary-color` | `#b9dbc2` | 10 | bp-admin |
| `--sp-secondary-dark` | `#3f5e49` | 2 | bp-admin |
| `--sp-secondary-darkest` | `#173522` | 9 | bp-admin |
| `--sp-secondary-contrast` | `#173522` | **0** | bp-admin |
| `--sp-accent-lightest` | `#ffe8cc` | 10 | bp-admin |
| `--sp-accent-light` | `#ffca90` | 6 | bp-admin |
| `--sp-accent-color` | `#ec9a3c` | 15 | bp-admin |
| `--sp-accent-dark` | `#bd6f00` | 2 | bp-admin |
| `--sp-accent-darkest` | `#944c00` | 8 | bp-admin |
| `--sp-accent-contrast` | `#1f2430` | 4 | bp-admin |
| `--sp-accent-hover` | `#bd6f00` | **0** | bp-admin |
| `--sp-accent-glow` | `rgba(236,154,60,0.18)` | **0** | bp-admin |
| `--sp-success` | `#2f8f57` | 17 | bp-admin |
| `--sp-success-light` | `#eaf3ee` | 1 | bp-admin |
| `--sp-warning` | `#c77d18` | 13 | bp-admin |
| `--sp-warning-light` | `#f9f0e3` | 1 | bp-admin |
| `--sp-danger` | `#d0432f` | 35 | bp-admin |
| `--sp-danger-light` | `#fbe9e6` | 3 | bp-admin |
| `--sp-text` | `var(--sp-primary-darkest)` | 7 | bp-admin |
| `--sp-text-secondary` | `var(--sp-secondary-darkest)` | 6 | bp-admin |
| `--sp-text-light` | `var(--sp-secondary-lightest)` | 2 | bp-admin |
| `--sp-sidebar-width` | `300px` | 5 | bp-admin |
| `--sp-button-shadow` | `1px 1px 3px transparent` | 3 | bp-admin |
| `--sp-button-shadow-hover` | `1px 1px 3px rgba(0, 0, 0, 0.20)` | 1 | bp-admin |
| `--sp-trend-pos` | `#1faa59` | 5 | bp-admin |
| `--sp-trend-neutral` | `#8b8b8b` | 1 | bp-admin |
| `--sp-trend-neg` | `#d23b3b` | 5 | bp-admin |
| `--sp-card-padding` | `16px` | 26 |  |
| `--sp-font` | `"Plus Jakarta Sans", "Open Sans", system-ui, -apple-system, "Segoe …` | 17 |  |
| `--sp-text-xs` | `clamp(12px, calc(10.857px + 0.286vw), 16px)` | 48 |  |
| `--sp-text-sm` | `clamp(13px, calc(11.857px + 0.286vw), 17px)` | 67 |  |
| `--sp-text-base` | `clamp(15px, calc(13.857px + 0.286vw), 19px)` | 50 |  |
| `--sp-text-md` | `clamp(17px, calc(15.857px + 0.286vw), 21px)` | 18 |  |
| `--sp-text-lg` | `clamp(20px, calc(18.857px + 0.286vw), 24px)` | 11 |  |
| `--sp-text-xl` | `clamp(24px, calc(22.857px + 0.286vw), 28px)` | 6 |  |
| `--sp-title` | `34rem` | 1 |  |
| `--sp-display` | `44rem` | 2 |  |
| `--sp-space-1` | `clamp(4px, calc(3.429px + 0.143vw), 6px)` | 104 |  |
| `--sp-space-2` | `clamp(8px, calc(7.429px + 0.143vw), 10px)` | 194 |  |
| `--sp-space-3` | `clamp(12px, calc(11.429px + 0.143vw), 14px)` | 177 |  |
| `--sp-space-4` | `clamp(16px, calc(15.429px + 0.143vw), 18px)` | 92 |  |
| `--sp-space-5` | `clamp(20px, calc(18.857px + 0.286vw), 24px)` | 49 |  |
| `--sp-space-6` | `clamp(24px, calc(22.857px + 0.286vw), 28px)` | 24 |  |
| `--sp-space-7` | `clamp(32px, calc(30.857px + 0.286vw), 36px)` | 11 |  |
| `--sp-space-8` | `clamp(48px, calc(46.857px + 0.286vw), 52px)` | 7 |  |
| `--sp-radius-md` | `12px` | 26 |  |
| `--sp-radius-xl` | `22px` | 8 |  |
| `--sp-control-h` | `48px` | 10 |  |
| `--sp-bar-h` | `64px` | 5 |  |
| `--sp-drawer-w` | `clamp(530px, calc(-309.839px + 81.935vw), 1165px)` | 1 |  |
| `--sp-surface` | `var(--sp-bg-white, #fff)` | 113 |  |
| `--sp-canvas` | `var(--sp-secondary-lightest)` | 27 |  |
| `--sp-ink` | `var(--sp-primary-darkest)` | 119 |  |
| `--sp-ink-2` | `color-mix(in oklab, var(--sp-ink) 76%, var(--sp-surface))` | 96 |  |
| `--sp-ink-3` | `color-mix(in oklab, var(--sp-ink) 58%, var(--sp-surface))` | 37 |  |
| `--sp-line` | `color-mix(in oklab, var(--sp-ink) 10%, var(--sp-surface))` | 92 |  |
| `--sp-line-strong` | `color-mix(in oklab, var(--sp-ink) 20%, var(--sp-surface))` | 23 |  |
| `--sp-well` | `var(--sp-canvas)` | 9 |  |
| `--sp-hover` | `color-mix(in oklab, var(--sp-brand) 5%, var(--sp-surface))` | 7 |  |
| `--sp-brand` | `var(--sp-primary-color)` | 97 |  |
| `--sp-brand-ink` | `var(--sp-primary-contrast)` | 44 |  |
| `--sp-brand-soft` | `color-mix(in oklab, var(--sp-brand) 8%, var(--sp-surface))` | 20 |  |
| `--sp-table-head` | `color-mix(in oklab, var(--sp-brand) 12%, var(--sp-surface))` | 3 |  |
| `--sp-table-head-ink` | `color-mix(in oklab, var(--sp-brand) 80%, var(--sp-ink))` | 3 |  |
| `--sp-brand-hover` | `color-mix(in oklab, var(--sp-brand) 84%, var(--sp-surface))` | 1 |  |
| `--sp-brand-tint` | `color-mix(in oklab, var(--sp-brand) 12%, var(--sp-surface))` | 3 |  |
| `--sp-sheen` | `color-mix(in oklab, var(--sp-surface) 35%, transparent)` | 5 |  |
| `--sp-shade` | `color-mix(in oklab, var(--sp-ink) 20%, transparent)` | 5 |  |
| `--sp-drop` | `color-mix(in oklab, var(--sp-ink) 24%, transparent)` | 3 |  |
| `--sp-drop-soft` | `color-mix(in oklab, var(--sp-ink) 12%, transparent)` | 2 |  |
| `--sp-brand-muted` | `color-mix(in oklab, var(--sp-brand-ink) 74%, var(--sp-brand))` | 4 |  |
| `--sp-mark` | `var(--sp-accent-color)` | 52 |  |
| `--sp-mark-ink` | `var(--sp-accent-contrast)` | 12 |  |
| `--sp-mark-hover` | `color-mix(in oklab, var(--sp-mark) 82%, var(--sp-ink))` | 1 |  |
| `--sp-mark-soft` | `color-mix(in oklab, var(--sp-mark) 24%, var(--sp-surface))` | 13 |  |
| `--sp-mark-line` | `color-mix(in oklab, var(--sp-mark) 55%, var(--sp-surface))` | 5 |  |
| `--sp-mark-strong` | `color-mix(in oklab, var(--sp-mark) 72%, var(--sp-ink))` | 11 |  |
| `--sp-danger-ink` | `color-mix(in oklab, var(--sp-danger) 82%, var(--sp-ink))` | 22 |  |
| `--sp-danger-soft` | `color-mix(in oklab, var(--sp-danger) 9%, var(--sp-surface))` | 4 |  |
| `--sp-warning-ink` | `color-mix(in oklab, var(--sp-warning) 70%, var(--sp-ink))` | 8 |  |
| `--sp-warning-soft` | `color-mix(in oklab, var(--sp-warning) 13%, var(--sp-surface))` | 4 |  |
| `--sp-success-ink` | `color-mix(in oklab, var(--sp-success) 80%, var(--sp-ink))` | 11 |  |
| `--sp-success-soft` | `color-mix(in oklab, var(--sp-success) 12%, var(--sp-surface))` | 2 |  |
| `--sp-shadow-1` | `0 1px 2px color-mix(in oklab, var(--sp-ink) 7%, transparent)` | 9 |  |
| `--sp-shadow-2` | `0 18px 44px color-mix(in oklab, var(--sp-ink) 24%, transparent)` | 9 |  |
| `--sp-focus` | `0 0 0 3px color-mix(in oklab, var(--sp-mark) 45%, transparent)` | 3 |  |
| `--sp-ease` | `180ms ease` | 48 |  |
| `--sp-z-bar` | `160` | 1 |  |
| `--sp-z-scrim` | `99990` | 1 |  |
| `--sp-z-sheet` | `99991` | 1 |  |
| `--sp-z-toast` | `100000` | 2 |  |

### style-schedules.css (54)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--color-main` | `#1a2744` | 39 |  |
| `--color-main-light` | `#dce3f0` | **0** |  |
| `--color-main-inverse` | `#ffffff` | 2 |  |
| `--color-accent` | `#c9a227` | 38 |  |
| `--color-accent-light` | `#faefc7` | 1 |  |
| `--color-accent-inverse` | `#ffffff` | 8 |  |
| `--s-color-1` | `#16a34a` | 11 |  |
| `--s-color-1-light` | `#dcfce7` | 4 |  |
| `--s-color-2` | `#dc2626` | 13 |  |
| `--s-color-2-light` | `#fee2e2` | 4 |  |
| `--s-color-3` | `#2563eb` | 5 |  |
| `--s-color-3-light` | `#dbeafe` | 5 |  |
| `--s-color-4` | `#7c3aed` | 2 |  |
| `--s-color-4-light` | `#ede9fe` | 2 |  |
| `--s-color-5` | `#ea580c` | 2 |  |
| `--s-color-5-light` | `#ffedd5` | 2 |  |
| `--s-color-6` | `#854d0e` | 3 |  |
| `--s-color-6-light` | `#fef9c3` | 2 |  |
| `--s-color-7` | `#047481` | 1 |  |
| `--s-color-7-light` | `#d1f2f6` | **0** |  |
| `--s-color-8` | `#e6af68` | 1 |  |
| `--s-color-8-light` | `#d1f2f6` | **0** |  |
| `--shift-a` | `var(--s-color-3)` | 6 |  |
| `--shift-a-light` | `var(--s-color-3-light)` | 4 |  |
| `--shift-b` | `var(--s-color-1)` | 6 |  |
| `--shift-b-light` | `var(--s-color-1-light)` | 4 |  |
| `--shift-c` | `var(--s-color-4)` | 6 |  |
| `--shift-c-light` | `var(--s-color-4-light)` | 4 |  |
| `--shift-d` | `var(--s-color-5)` | 6 |  |
| `--shift-d-light` | `var(--s-color-5-light)` | 4 |  |
| `--button-gap` | `10px` | 2 |  |
| `--btn-primary-color` | `var(--color-accent-inverse)` | 1 |  |
| `--btn-primary-bg` | `var(--color-accent)` | 1 |  |
| `--btn-primary-border` | `var(--color-accent)` | 1 |  |
| `--btn-primary-color-hover` | `var(--color-main-inverse)` | 1 |  |
| `--btn-primary-bg-hover` | `var(--color-main)` | 1 |  |
| `--btn-primary-border-hover` | `var(--color-main)` | 1 |  |
| `--btn-secondary-color` | `var(--color-main-inverse)` | 1 |  |
| `--btn-secondary-bg` | `var(--color-main)` | 1 |  |
| `--btn-secondary-border` | `var(--color-main)` | 1 |  |
| `--btn-secondary-color-hover` | `var(--color-accent-inverse)` | 1 |  |
| `--btn-secondary-bg-hover` | `var(--color-accent)` | 1 |  |
| `--btn-secondary-border-hover` | `var(--color-accent)` | 1 |  |
| `--btn-danger-color` | `var(--white)` | 1 |  |
| `--btn-danger-bg` | `var(--s-color-2)` | 1 |  |
| `--btn-danger-border` | `var(--s-color-2)` | 1 |  |
| `--btn-danger-color-hover` | `var(--white)` | 2 |  |
| `--btn-danger-bg-hover` | `#b91c1c` | 2 |  |
| `--btn-danger-border-hover` | `#b91c1c` | 2 |  |
| `--button-opacity-disabled` | `.55` | 1 |  |
| `--button-cursor-disabled` | `not-allowed` | 1 |  |
| `--container-radius` | `10px` | 36 |  |
| `--container-shadow` | `0 1px 3px rgba(0,0,0,.1), 0 1px 2px rgba(0,0,0,.06)` | 9 |  |
| `--container-shadow-lg` | `0 10px 25px rgba(0,0,0,.15)` | 4 |  |

### style-store.css (21)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--store-gap` | `24px` | 3 |  |
| `--store-radius` | `8px` | 18 |  |
| `--store-border` | `1px solid var(--lightest-grey)` | 23 |  |
| `--store-border-active` | `1px solid var(--medium-grey)` | 3 |  |
| `--product-img-border` | `var(--store-border)` | 2 |  |
| `--product-img-border-active` | `var(--store-border-active)` | **0** |  |
| `--variation-tile-border` | `2px solid var(--lightest-grey)` | 2 |  |
| `--variation-tile-border-active` | `2px solid var(--medium-grey)` | 4 |  |
| `--store-card-bg` | `var(--white)` | 7 |  |
| `--store-muted` | `var(--medium-grey)` | 19 |  |
| `--store-title-color` | `var(--text-color)` | 4 |  |
| `--store-price-color` | `var(--text-color)` | 1 |  |
| `--store-sale-color` | `var(--warning)` | 5 |  |
| `--store-was-color` | `var(--store-muted)` | 1 |  |
| `--store-soldout-bg` | `var(--black-high)` | 2 |  |
| `--store-soldout-color` | `var(--white)` | 2 |  |
| `--store-btn-bg` | `var(--button-background)` | 2 |  |
| `--store-btn-color` | `var(--button-color)` | 3 |  |
| `--store-btn-bg-hover` | `var(--button-background-hover)` | 2 |  |
| `--store-btn-color-hover` | `var(--button-color-hover)` | 3 |  |
| `--store-cols` | `3` | 2 |  |

### style-admin.css (6)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--main-blue` | `rgba(34, 112, 175, 1)` | 9 |  |
| `--pale-blue` | `rgba(207, 218, 241, 1)` | 2 |  |
| `--pale-red` | `rgba(244, 217, 217, 1)` | 1 |  |
| `--pale-blue-75` | `rgba(207, 218, 241, 0.75)` | 1 |  |
| `--pale-red-75` | `rgba(244, 217, 217, 0.75)` | 1 |  |
| `--inline-edit` | `2px dotted var(--main-blue)` | 6 |  |

### style-bp-admin.css (15)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--sp-radius-xs` | `calc(var(--sp-radius) * 0.5)` | 1 |  |
| `--sp-radius-circle` | `50%` | **0** |  |
| `--sp-priority-high` | `var(--sp-danger)` | **0** |  |
| `--sp-priority-medium` | `#f97316` | **0** |  |
| `--sp-priority-low` | `#eab308` | **0** |  |
| `--sp-shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | 1 |  |
| `--sp-shadow-lg` | `0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)` | **0** |  |
| `--sp-transition` | `0.2s ease` | 6 |  |
| `--sp-white` | `#ffffff` | **0** |  |
| `--sp-bg` | `#f2f3f6` | 1 |  |
| `--sp-bg-white` | `#ffffff` | 2 |  |
| `--sp-border` | `#b9dbc2` | 4 |  |
| `--card-border-radius` | `24px` | 1 |  |
| `--card-padding` | `2em` | 9 |  |
| `--sp-god-h` | `0px` | 13 |  |

### style-customer-connect.css (17)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--cc-font` | `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica N…` | 1 |  |
| `--cc-brand` | `#0f766e` | 6 |  |
| `--cc-brand-ink` | `#ffffff` | 3 |  |
| `--cc-brand-2` | `#f59e0b` | 2 |  |
| `--cc-brand-2-ink` | `#ffffff` | 1 |  |
| `--cc-white` | `#ffffff` | **0** |  |
| `--cc-bg` | `#f4f6f8` | 4 |  |
| `--cc-surface` | `#ffffff` | 2 |  |
| `--cc-ink` | `#1f2933` | 11 |  |
| `--cc-ink-soft` | `#52606d` | 9 |  |
| `--cc-ink-faint` | `#9aa5b1` | **0** |  |
| `--cc-line` | `#e2e6ea` | 5 |  |
| `--cc-radius` | `0.9em` | 2 |  |
| `--cc-shadow` | `0 0.15em 0.5em rgba(0, 0, 0, .06)` | 2 |  |
| `--cc-tabbar-h` | `4.2em` | **0** |  |
| `--cc-accent` | `var(--cc-brand)` | 12 |  |
| `--cc-accent-ink` | `var(--cc-brand-ink)` | 2 |  |

### style-home-base.css (10)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--hb-accent` | `#0f766e` | 15 |  |
| `--hb-bg` | `#f4f6f8` | 3 |  |
| `--hb-surface` | `#ffffff` | 3 |  |
| `--hb-ink` | `#1f2933` | 10 |  |
| `--hb-ink-soft` | `#52606d` | 9 |  |
| `--hb-line` | `#e2e6ea` | 5 |  |
| `--hb-radius` | `0.9em` | 2 |  |
| `--hb-shadow` | `0 0.15em 0.5em rgba(0, 0, 0, .06)` | 2 |  |
| `--hb-tabbar-h` | `4.2em` | 2 |  |
| `--hb-accent-ink` | `#ffffff` | 2 |  |

### style-ai-chat.css (25)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--chatbot-launcher-border` | `var(--button-border)` | 1 |  |
| `--chatbot-launcher-border-radius` | `100%` | 1 |  |
| `--chatbot-launcher-background` | `var(--button-background)` | 1 |  |
| `--chatbot-launcher-color` | `var(--button-color)` | 1 |  |
| `--chatbot-launcher-border-hover` | `var(--button-border-hover)` | 1 |  |
| `--chatbot-launcher-border-radius-hover` | `100%` | 1 |  |
| `--chatbot-launcher-background-hover` | `var(--button-background-hover)` | 1 |  |
| `--chatbot-launcher-color-hover` | `var(--button-color-hover)` | 1 |  |
| `--chatbot-shadow` | `-5px 5px 10px var(--black)` | 3 |  |
| `--chatbot-header-font-family` | `var(--font-text)` | 1 |  |
| `--chatbot-header-bg` | `var(--dark-grey)` | 2 |  |
| `--chatbot-header-color` | `var(--lightest-grey)` | 2 |  |
| `--chatbot-consent-bg` | `var(--chatbot-header-bg)` | 1 |  |
| `--chatbot-consent-color` | `var(--chatbot-header-color)` | 1 |  |
| `--chatbot-radius` | `1em` | 3 |  |
| `--chatbot-bubble-bg` | `var(--lightest-grey)` | 1 |  |
| `--chatbot-bubble-color` | `var(--black)` | 2 |  |
| `--chatbot-user-bubble-bg` | `var(--light-grey)` | 1 |  |
| `--chatbot-user-bubble-color` | `var(--black)` | 1 |  |
| `--chatbot-muted` | `var(--black)` | 1 |  |
| `--chatbot-links` | `var(--button-color)` | 1 |  |
| `--chatbot-bubble-link` | `var(--chatbot-bubble-color)` | 1 |  |
| `--chatbot-form-padding` | `1em` | 1 |  |
| `--chatbot-form-border` | `2px solid var(--dark-grey)` | 1 |  |
| `--chatbot-form-background` | `var(--medium-grey)` | 1 |  |

### style-events.css (17)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--calendar-border-width` | `1px` | 7 | jobsite-geo-admin, jobsite_geo |
| `--calendar-border-style` | `solid` | 7 | jobsite-geo-admin, jobsite_geo |
| `--calendar-border-color` | `rgba(0, 0, 0, 0.2)` | 9 | jobsite-geo-admin, jobsite_geo |
| `--calendar-background` | `var(--white)` | 3 | jobsite-geo-admin, jobsite_geo |
| `--calendar-title-color` | `var(--text-color)` | 1 | jobsite-geo-admin, jobsite_geo |
| `--calendar-title-size` | `var(--font-size-h6)` | 1 | jobsite-geo-admin, jobsite_geo |
| `--calendar-day-color` | `var(--text-color)` | 3 | jobsite-geo-admin, jobsite_geo |
| `--calendar-day-background` | `var(--calendar-background)` | 4 |  |
| `--calendar-day-color-today` | `var(--calendar-day-color)` | 2 |  |
| `--calendar-day-background-today` | `var(--calendar-day-background)` | 1 |  |
| `--calendar-day-outline-today` | `1px solid var(--calendar-day-color-today)` | 1 |  |
| `--calendar-day-color-event` | `var(--calendar-day-color)` | 3 |  |
| `--calendar-day-background-event` | `var(--calendar-day-background)` | 1 |  |
| `--calendar-day-outline-event` | `2px solid var(--calendar-day-color-event)` | 1 |  |
| `--calendar-day-background-empty` | `var(--calendar-day-background)` | 1 |  |
| `--calendar-link-color` | `var(--text-color)` | 1 |  |
| `--calendar-link-color-hover` | `var(--text-color)` | 1 |  |

### style-timeline.css (16)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--timeline-line-color` | `var(--black)` | 6 |  |
| `--timeline-line-width` | `3px` | 2 |  |
| `--timeline-year-dot-width` | `130px` | 1 |  |
| `--timeline-year-dot-font-size` | `var(--font-size-h3)` | 1 |  |
| `--timeline-year-dot-font-weight` | `bold` | 1 |  |
| `--timeline-year-dot-font-color` | `var(--white)` | 1 |  |
| `--timeline-year-dot-bg-color` | `var(--black)` | 1 |  |
| `--timeline-year-dot-aspect-ratio` | `1/1` | 1 |  |
| `--timeline-year-dot-border-radius` | `100%` | 1 |  |
| `--timeline-year-dot-border-width` | `3px` | 2 |  |
| `--timeline-year-dot-border-color` | `var(--white)` | 1 |  |
| `--timeline-story-border` | `2px solid var(--black)` | 3 |  |
| `--timeline-story-headline-bg` | `var(--white)` | 2 |  |
| `--timeline-story-headline-color` | `var(--black)` | 2 |  |
| `--timeline-story-content-bg` | `var(--timeline-story-headline-color)` | 1 |  |
| `--timeline-story-content-color` | `var(--timeline-story-headline-bg)` | 1 |  |

### style-paypal.css (9)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--pp-max-width` | `480px` | 2 |  |
| `--pp-gap` | `16px` | 1 |  |
| `--pp-radius` | `8px` | 2 |  |
| `--pp-border` | `1px solid var(--light-grey)` | 2 |  |
| `--pp-background` | `var(--white)` | 1 |  |
| `--pp-label-color` | `var(--medium-grey)` | 4 |  |
| `--pp-text-color` | `var(--text-color)` | 3 |  |
| `--pp-error` | `var(--warning)` | 5 |  |
| `--pp-ok` | `rgba(20, 140, 70, 1)` | 1 |  |

### style-jobsite_geo.css (3)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--calendar-background-today` | `rgba(0, 0, 0, 0.1)` | 1 | jobsite-geo-admin |
| `--calendar-background-empty` | `rgba(0, 0, 0, 0.025)` | **0** | jobsite-geo-admin |
| `--calendar-day-today-color` | `var(--darkest-grey)` | **0** | jobsite-geo-admin |

### style-woocommerce.css (1)

| Token | Value | Uses | Also defined in |
|---|---|---|---|
| `--woo-max-img-w` | `400px` | 2 |  |
