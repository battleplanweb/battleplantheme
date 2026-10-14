# Battle Plan Theme — CSS architecture

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

## CSS Architecture

One CSS file per feature/component. All loaded conditionally based on what's on the page.
Site-specific overrides go in `style-site.css` in the child theme.

### FIRM RULE — reuse existing classes; never rebuild what's already styled

**Before writing any CSS for a new element or component, CHECK the existing CSS for a global/shared class that already fits.** The framework and `style-site.css` already style most common things (buttons, cards, lists, sections, layout columns, etc.). If a class exists that does the job, **use it.** Only after reusing the shared class do you add a narrower rule for the *specific differences* that element needs.

**Do not write self-contained rules for individual elements when a class already handles elements just like it.** That produces the exact redundancy Glendon is trying to eliminate — every ID re-declaring the same shared properties, with only one line actually different between them.

Wrong — every property re-declared per element, shared values duplicated:
```css
#box-1 { width: 50%; color: blue; background: red; }
#box-2 { width: 50%; color: blue; background: green; }
#box-3 { width: 75%; color: blue; background: red; }
```

Right — one base class holds the common case; each specific rule overrides *only* what differs:
```css
.box   { width: 50%; color: blue; background: red; }  /* the shared default */
#box-2 { background: green; }                          /* only the difference */
#box-3 { width: 75%; }                                 /* only the difference */
```

So the working order is always:
1. **Search existing CSS first** (framework `style.css`, feature CSS files, `style-site.css`) for a class that already fits — reuse it.
2. If it *almost* fits, apply the shared class **and** add a narrower rule (ID or more specific selector) that changes **only** the properties that differ. Never restate inherited values.
3. Only when nothing shared fits do you author a genuinely new class — and if the pattern will recur, make it a **class**, not an ID, so the next element can reuse it too.

This applies to the framework's variable-driven systems too: prefer setting the existing `--button-*` / color / spacing variables on a scope over hand-writing full property blocks (see the button and bullet-list sections below).

### Layout: default to `display: grid`, override to `flex` only with a reason

**Always reach for `display: grid` first.** It is the default layout tool for this framework — page sections, forms, card galleries, anything where content lines up in two dimensions (rows *and* columns). Glendon prefers grid and uses it for everything, so grid should be the assumption on every new build unless there is a specific reason not to.

**Only switch to `display: flex` in these few cases, where flex is genuinely better — not as a style choice:**

- **Content-sized items along one axis** — a row of items of *varying* widths that should each take only the space they need (toolbars, button rows, nav items). Grid's `repeat(auto-fit, minmax())` forces equal-width tracks; flex sizes to content.
- **Push-to-end / split bars** — using `margin-left: auto` (or `margin-inline-start: auto`) to shove remaining items to the far end of a bar (logo left, buttons right). Flex absorbs free space along the main axis; grid can't do this cleanly.
- **Independently-packing wrap** — `flex-wrap` where each row packs on its own and you *don't* want columns aligned across rows (tag/chip rows, breadcrumbs). Grid's auto-fit keeps everything column-aligned, which is wrong for chip-like content.
- **Unknown / dynamic item counts on one axis** — a single row or column of a variable number of children that shouldn't be forced into a rigid matrix.

Mental test: *"Do I care that things line up in the other direction too?"* Yes → grid. Only one axis, and content should drive sizing → flex.

**When you do use flex, add a short comment saying WHY** — so Glendon can learn the flex cases over time. Example:

```css
/* flex, not grid: buttons are different widths and should size to content */
.cta-row { display: flex; flex-wrap: wrap; gap: 0.5em; }

/* flex, not grid: margin-auto pushes the nav to the right of the bar */
.site-header { display: flex; align-items: center; }
.site-header .nav { margin-inline-start: auto; }
```

If a layout could plausibly go either way, choose grid.

### Units — `px` everywhere, `rem` only for headline font sizes. Never `em`.

**Glendon does not want `em` in site CSS.** Write `px`. The single exception is **headline
font sizes** (`--font-size-h1`…`h6`, `--h-font-size`, and a heading's sub-head span), which
stay in `rem` so they keep shrinking on small screens.

Why the split — measured in a live build, not inferred:

| declared | at 1600px | at 900px | at 576px |
|---|---|---|---|
| `100px` | 100px | 100px | 100px |
| `100rem` | 100px | 80px | 76px |
| `100em` on body-sized text | 1800px | 1700px | 1600px |

- **`px` is fixed** at every width. That is what you want for padding, gaps, borders, radii,
  icon sizes, max-widths, card metrics — anything whose size shouldn't drift.
- **`rem` is ~1:1 with px on desktop and auto-shrinks** (~0.80x below 1024, ~0.76x below 576).
  That free step-down is exactly right for headlines, which is why the `--font-size-h*` tokens
  are authored in `rem` — `--font-size-h1: 136rem` renders 136px on desktop and ~103px on a
  phone with no media query. Because `rem` is ~1px here, **read the number as pixels**.
- **`em` resolves against whatever font-size the element happened to inherit**, so the same
  `0.9em` means something different in a card, a button and a widget. That unpredictability
  is the reason it's banned.

**Whole pixels only — no fractional px.** `11.2px` is `11px`, `22.5px` is `23px`. Round half
away from zero, so `-30.6px` is `-31px` and `0.5px` is `1px`, not `0`. This matters most right
after an `em` conversion, which produces long decimals by construction (`0.72em` x `17.1px` =
`12.312px`) — round them as you write them, don't leave the arithmetic showing.

Two traps when you automate it:
- **Most languages' default rounding is not what you want.** Python's `round()` is banker's
  rounding: `round(22.5)` is `22` and `round(0.5)` is `0`. Use `floor(abs(x) + 0.5)` with the
  sign reapplied, or half-up rounding will silently disagree with the rule on every `.5`.
- **Watch values that round to zero.** A sub-pixel `letter-spacing: 0.4px` becomes `0` and the
  tracking disappears. That is usually the right call (0.4px was doing nothing), but check the
  list rather than letting it happen unseen — and if the effect was wanted, it needs a real
  whole-pixel value, not a fraction.

Two consequences worth knowing before you convert an existing site's `em` values to `px`:
- **You lose the automatic mobile shrink.** Anything that was in `em` was quietly getting
  ~24% smaller on phones; in `px` it won't. Budget a pass over the mobile queries afterwards.
- **Sizes that should track the type still need to.** A value deliberately tied to its
  heading's size (a mask tile, an optical margin under a headline) belongs in `em` conceptually
  — express it in `rem` alongside the heading token instead, or set it per breakpoint in `px`.

(Supersedes the older "use `em`, not `rem`" note that came out of the CCSO schedules module.)

### style-site.css is COMPILED — it is never served directly

`bp_build_site_css()` (in `functions-style-sheets.php`, on `after_setup_theme`) compiles `style-site.css` → `dist/site.css` + `dist/site.min.css`. The browser is served **`dist/site.min.css?ver=_BP_VERSION`** (the `battleplanweb` admin user gets the unminified `dist/site.css`). Editing `style-site.css` alone is not enough to see changes:
- **Rebuild is content-hash-gated** — `dist/` regenerates whenever the source content changes, tracked via a `dist/*.sig` file holding an md5 of the source (`site.css.sig` for the site build; `core-critical.css.sig` / `core-deferred.css.sig` for core). This is deploy-stable. (It used to be mtime-gated, which silently broke: git/WP Engine checkouts stamp every file with the same time, so a content-updated `style-site.css` and a stale committed `dist/site.min.css` tied on mtime and the rebuild was skipped — sites served old compiled CSS indefinitely. Fixed June 2026.)
- **Cache-bust is `_BP_VERSION . '.' . filemtime(dist file)`** — the enqueue `?ver=` now includes the compiled file's mtime, which `file_put_contents` bumps on every rebuild. So a rebuild changes the asset URL and busts EverCache/Cloudflare automatically; no manual purge needed for CSS edits. (The inline critical CSS is echoed straight into `wp_head`, so it's never cached separately.)

So after changing `style-site.css`, just deploy it — the next front-end pageload rebuilds `dist/` (hash changed) and the new `?ver` busts the CDN. To verify quickly, log in as `battleplanweb` (served the unminified `dist/site.css`). If a site still "ignores" your CSS, check that `dist/site.css.sig` matches `md5(style-site.css)` on the server.

### Page content has NO comment syntax

Content pasted into the editor / Page Top / Page Bottom / Elements is shortcode + HTML only. `/* … */` and `// …` are **not** stripped — they render as literal text on the page. When delivering paste-in markup (e.g. a `page-content.txt`), keep all labels/instructions OUTSIDE the copyable blocks; never put comments inside a block the user will paste.

Key CSS variable groups (defined in `:root` in `style-site.css`):
- `--font-primary`, `--font-secondary`, `--font-tertiary`, `--font-text`
- Color palette: `--main-red`, `--main-blue`, `--black`, `--white`, `--light-grey`, etc.

### NEVER put animated OR TRANSFORMED text inside a `background-clip: text` element

A textured headline — `background-image` + `background-clip: text` + `-webkit-text-fill-color: transparent` — clips that texture to **every glyph inside the element, descendants included**. Put `animateCharacters()` (or any transform/opacity animation) on text inside it and the headline renders **twice**: the real characters animating correctly, plus a ghost copy of them piled up at the container's origin, textured, for the whole animation. Chrome promotes an animating element to its own compositor layer, and a promoted descendant is drawn at the wrong coordinates inside the ancestor's text-clip mask.

**Animation is not the trigger — compositor promotion is, and a plain static `transform`
does it too.** Verified on Heartland (Sept 2026): a headline whose second line carried
`transform: scale(0.7, 1)` — no animation anywhere — ghosted exactly the same way the moment
the parent `h1` got `background-clip: text`. Removing the transform made the ghost vanish;
removing the clip did too. So read this rule as: **nothing inside a text-clipped element may
carry a `transform`, a `filter`, `will-change`, or an animation.** The fix is the same one
below — move the texture off the ancestor and onto the individual static line.

**There is no CSS fix on the animated element.** `isolation: isolate`, `will-change: transform`, `transform: translateZ(0)`, and forcing `transform: none` were all tested against a live case — the ghost survives every one. Clearing the animation on `animationend` only hides it *after* the animation, which is the wrong half.

The texture has to come **off the animated text's ancestor**. Keep the headline plain and move the texture onto the static lines, each in its own span:

```html
<div class="headline"><span class="textured">New Customer Special</span><br>
 <span class="amount">$20 OFF</span><br>
 <span class="textured">Your Service Call</span></div>
```
```css
#coupon .headline  { background-image: none; -webkit-background-clip: border-box; background-clip: border-box;
                     -webkit-text-fill-color: var(--white); color: var(--white); }
#coupon .headline .textured { background-image: url('/wp-content/uploads/font-grunge-text.webp'); background-size: cover;
                     -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
```

The animated span inherits the plain fill from the headline and animates cleanly; the static lines keep the texture. Same rule applies to `h1`/`h2` when a site textures them globally — check what's *inside* a textured heading before animating it. (Diagnosed on Blu Collar Mechanical's `#coupon`, Aug 2026.)

### Custom brand headline fonts — ALWAYS ship a metric-matched fallback

A heavy/wide display font (e.g. Rammetto One) used for headlines will, before it loads, fall back to a plain system font at the same `font-size` — which renders **small, thin, and re-wrapped**. That's an ugly flash *and* a large CLS when the real font finally swaps in (the headline re-wraps, changing its height). `font-display: block` doesn't fix it (invisible text, then the same shift); `optional` fixes CLS but shows the wrong-sized fallback on every cold/throttled load (which is exactly what PSI-mobile lab tests). The real fix is a companion fallback `@font-face` that scales a **local** system font to the brand font's exact box:

- Keep the brand `@font-face` on **`font-display: swap`** (not `block`/`optional`).
- Add a `'<Brand> Fallback'` face using `local('Arial')` + `size-adjust` / `ascent-override` / `descent-override` / `line-gap-override`, and put it in the stack **right after** the brand font:
  `--font-primary: '<Brand>', '<Brand> Fallback', Helvetica, Arial, sans-serif;`
- The fallback now occupies the identical box → the swap is shift-free (**~0 CLS**) and the cold-load fallback is already the right size/width. Brand font still appears the instant it loads, on every visit.

**Easiest — run the ready-made generator** (handles caps-vs-mixed + serif-vs-sans automatically):
```
node "00 - Battle Plan Assets/tools/font-fallback/font-fallback.mjs" "<path to the site or its style-site.css>"
```
It prints the `@font-face` to paste, the `font-display: swap` reminder, and the `--font-primary` line. Point it at a raw font file with `--mixed` / `--serif` / `--name` to override detection. (See that folder's README.)

**Or by hand with Capsize — never eyeball them:**
```
npm i @capsizecss/core @capsizecss/metrics
createFontStack([brand, arial]).fontFaces   // brand from @capsizecss/metrics/<fontCamelCase>, or unpack the woff2
```

**GOTCHA — all-caps headlines need a caps-measured `size-adjust`.** Capsize's `size-adjust` comes from `xWidthAvg`, a **lowercase**-frequency-weighted average. Our brand headlines are almost always **all-caps**, and display fonts often have caps whose width differs wildly from their lowercase (Blu Collar's "Ron" font: caps are **65%** of Arial, but Capsize said 92% off the lowercase avg → the fallback rendered ~40% too wide). When the headline is uppercase, compute `size-adjust` from actual **caps** advances instead, then rescale the vertical overrides by the same factor:
```
import * as fontkit from 'fontkit';                       // npm i fontkit
const norm = (f, s) => f.layout(s).advanceWidth / f.unitsPerEm;
const brand = fontkit.openSync('<brand>.ttf'), arial = fontkit.openSync('C:/Windows/Fonts/arial.ttf');
const sa = norm(brand, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') / norm(arial, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
// size-adjust = sa; ascent-override = brand.ascent/brand.unitsPerEm/sa; descent = |descent|/em/sa; line-gap = lineGap/em/sa
```
Verify by blocking the woff2 in DevTools (Network → Block request URL) — the fallback headline should match the real one's width.

Worked example (Blu Collar → Arial, caps-measured: `size-adjust: 65.0412%`) lives in that site's `style-site.css`.

### Every `:hover` rule must also style `.active` and `.tab-focus` (ADA)

The framework is built for ADA/keyboard compliance: when a user tabs to an element, the framework applies `.active` / `.tab-focus` classes to it so it can show the **same** affordance a mouse user gets on hover. So **any selector you write with `:hover` must include matching `.active` and `.tab-focus` selectors** — never style `:hover` alone, or keyboard users get no visible focus state.

```css
a:hover,
a.active,
a.tab-focus {
	color: var(--blue);
}
```

This applies to **every** hoverable element, not just links — buttons, cards, icons, menu items, etc. Pattern: for any `X:hover`, add `X.active, X.tab-focus` alongside it.

```css
.service-card:hover,
.service-card.active,
.service-card.tab-focus {
	box-shadow: 0 8px 24px rgba(0,0,0,.15);
}
```

### Headings are variable-driven — style by `--h-*`, and use the tag for OUTLINE, not for size

`h1`–`h6` are styled entirely through `--h-*` custom properties (`--h-font-size`, `--h-font-family`, `--h-font-weight`, `--h-color`, `--h-line-height`, `--h-letter-spacing`, `--h-text-align`, `--h-margin`, `--h-transform`, …). Each tag just carries a different `--h-font-size` default (`h2` → `--font-size-h2`, and so on). **So pick the heading tag for the document outline — h1, then h2, then h3, in order — and get the look you want by overriding the variable.** No more `h1 → h4 → h2` on a page just because h4 happened to be the right size; that's an accessibility failure the variables now make unnecessary.

```css
#services h2       { --h-font-size: var(--font-size-h4); }   /* h2 in the outline, h4-sized on screen */
#hero h1 span      { --h-font-size: 20px; }                  /* sub-head inside the headline */
```

**A `<span>` / `<a>` inside a heading reads the tokens too**, via `:is(h1, h2, h3, h4, h5, h6) > :is(a, span)` — so the two-line headline pattern works with nothing but a variable:

```html
<h1>Headline<br><span>Sub-head</span></h1>
```
```css
h1 span { --h-font-size: 20px; }
```

**A sub-head span defaults to one size step down** — `h1 > span` gets `--font-size-h2`, `h2 > span` gets `--font-size-h3`, and so on, declared alongside each heading's own default. So the pattern is already correctly proportioned before you write any CSS; set `--h-font-size` on the span only when you want something other than the next step down.

Three things to know about how the consumer block is built, because all three are load-bearing:

- **Declaring the variable is not enough — something has to *consume* it.** The `font-size: var(--h-font-size)` declaration lives in the `h1…h6` rule, which a `<span>` does not match. Before the consumer block existed, `h1 span { --h-font-size: 20px }` set the variable perfectly and changed nothing on screen (the span was just inheriting the heading's computed `font-size`). DevTools tells you which case you're in: inspect the span → **Computed** → the custom property shows `20px` while `font-size` still shows the heading's value.
- **The tokens default to `initial`, not `inherit`, in that block** — `initial` is the *guaranteed-invalid* value, so `var(--h-font-size, inherit)` falls through to `inherit` and the element keeps whatever the heading computed. That matters for `> a`, and for every token a span doesn't get a step-down default for: plenty of sites set a **raw** `font-size` on a heading (`.section.style-alt h2 { font-size: 2.6em }`) rather than the token, and an unconditional `font-size: var(--h-font-size)` would *beat* inheritance — an author declaration outranks an inherited value no matter how specific the parent's rule was — snapping the child back to the framework token size. **Keep that shape if you add another `--h-*` consumer.**
- **The one place that safety doesn't reach is a span's `--h-font-size`,** because the step-down rules set it explicitly. On a heading sized by a raw `font-size` rather than the token, a sub-head span renders at the framework's step-down token, not at a proportion of what the heading actually shows. Size that heading with `--h-font-size` and the two stay in step.

**Selector shape matters here.** The consumer block uses `:is()`, not `:where()`: `:is()` scores its most specific argument, and since every argument is a type selector the whole thing scores exactly what `h1 > span` scores (0-0-2) — sites keep overriding it with plain `h1 span { … }`. Two traps if you rewrite it: `:where()` would zero the specificity and drop the block into a tie with the framework's own `a { }` rule further down the file, and folding the old `a:hover` / `a:visited` / `a.tab-focus` variants into the child `:is()` would score *every* branch at 0-1-2 — including `span` — so the framework would start outranking site overrides. (Those `a` state variants were dropped: they only ever existed to out-specify the link rules, which declare `color` / `background-color` / `cursor` / `transition` and never touch the font properties this block sets.)

**A span in a heading is a SUB-HEAD by default — the framework already blocks it out for you.** Write the markup and set tokens; nothing else:

```html
<h1>Headline<br><span>Sub-head</span></h1>
```
```css
h1 span { --h-font-size: var(--font-size-h2); --h-line-height: 0.5; margin-top: -30px; }
```

The framework supplies `display: block` on `:where(h1…h6) > span` plus a rule hiding the `<br>` in front of it. Both are mechanism, not design — **don't restate them in `style-site.css`.**

Why `display: block` is load-bearing rather than cosmetic: `--h-font-size` works on an inline span, but `--h-line-height` does **not**, and neither do vertical margins. Every line box carries a **strut** — an invisible box sized by the *block container's* own `font-size` × `line-height` — and the line box can never be shorter than it, so an inline span asking for tighter leading is simply overruled by the heading. `margin-top` doesn't apply to non-replaced inline elements at all. Both declarations sit there looking correct and doing nothing. The `<br>` gets hidden because a `<br>` immediately before a block-level sibling leaves a *second, empty* line box at the heading's full strut height — the classic mystery gap.

**The rare inline highlight opts out with `class="inline"`:**

```html
<h2>Call <span class="inline">Today</span> — we answer</h2>
```

The default rule is `:where(…)`-wrapped, so its specificity is 0-0-1 and *any* site rule outranks it; `h2 span { display: inline }` in `style-site.css` works just as well for a whole site or section.

**The shape to watch** when a heading isn't behaving: a span at the *start* of a heading followed by loose text — `<h1><span>Welcome to</span> Blu Collar</h1>` — now puts "Welcome to" on its own line. That's the one silent false positive of defaulting sub-head on. Add `.inline` when you hit it.

And expect to re-tune values the first time an existing site's span becomes a block: its line-height and margin were inert until that moment, so the sub-head can jump a long way when they take effect together.

**Size tokens are `rem` (`--font-size-h1: 48rem`), so nesting never compounds.** If you set `--h-font-size` in `em` on a span, it resolves against the parent heading's computed size and doubles up — use `rem`/`px`, or the existing tokens.

`--h-color` is intentionally **not** consumed on the span/link block: that selector list includes `> a:hover`, so a `color` declaration there would stomp link hover colors fleet-wide. To make span color overridable on a site, split the `> span` selectors into their own rule.

### Buttons are variable-driven — set `--button-*`, don't override `.button` directly

The framework's `.button` (and form-submit) styling is entirely CSS-custom-property driven. To restyle buttons in a scope, **set the `--button-*` variables on that scope** instead of writing `background`/`color`/`border` on `a.button`. Every visual property has a `-hover` companion, so you also **don't need a separate `:hover, :active, .tab-focus` block** — the framework already wires all those states (including the ADA `.tab-focus`/`.active` classes) to the `-hover` variables.

```css
#products .span-all a.button {
	--button-background:        var(--main-red);
	--button-border:            2px solid var(--main-red);
	--button-color:             var(--white);

	--button-background-hover:  var(--black);
	--button-border-hover:      2px solid var(--black);
	--button-color-hover:       var(--yellow);
}
```

That single block replaces *both* the base rule and the hand-written hover/active/tab-focus rule.

Common variables (each has a matching `--…-hover` that defaults to the base value if unset):
- `--button-color`, `--button-background`, `--button-border` (full border shorthand, e.g. `2px solid …`; default `none`)
- `--button-border-radius`, `--button-padding`, `--button-box-shadow`, `--button-transform`
- `--button-font-family`, `--button-font-size`, `--button-font-weight`, `--button-text-transform`, `--button-letter-spacing`, `--button-text-shadow`, `--button-outline`, `--button-filter`
- `--button-transition`

Set only the ones you're changing; the rest inherit framework defaults. **Don't** write `a.button { background: … }` plus a separate `:hover` block — that bypasses the variable system and re-does work the framework already handles. (Note: it's `--button-border`, not `--butotn-border`.)

### Styled lists — use the framework `.bullet` system, don't hand-roll list CSS

The framework ships a bullet/icon-list system (in `style.css`). For a checkmark/icon list, add a base class + a glyph modifier to the `<ul>` — **do not** build `<li>` rows out of `[get-icon]` + `<span>` + custom flex CSS. One class swaps the marker; no per-item markup.

```
<ul class="why-list bullet bullet-check">
 <li><strong>Rapid Response</strong> — Our fleet is ready to dispatch statewide.</li>
 <li><strong>Compliance Experts</strong> — We help you meet NFPA and state requirements.</li>
</ul>
```

**Base classes** (pick one):
- `bullet` — hanging marker; label + description stay inline on one line.
- `bullet-list` — same, but a leading `<strong>`/`<b>` breaks onto its own line (label above, text below).
- `fancy-list` — two-column: bold label in a fixed 160px column, text beside it.

**Glyph modifiers** (combine with a base class — set the `::before` marker):
| Class | Marker |
|---|---|
| *(none)* | `●` filled circle |
| `bullet-check` | ✔ |
| `bullet-square` | ■ |
| `bullet-diamond` | ♦ |
| `bullet-warning` | ⚠️ |
| `bullet-image` | image marker (default `/wp-content/uploads/site-icon-80x80.webp`) |
| `bullet-svg` | SVG-mask marker, tintable via `currentColor` (default = arrow) |

The marker inherits the list's text color. To tint **just** the marker, one rule does it (replacing all the hand-rolled flex/icon CSS): `#why-choose .why-list li::before { color: var(--yellow); }`.

**Custom marker icon:** use `bullet-svg` plus an extra modifier class of your choosing, then supply the SVG as a mask in a single rule (the marker fills with `currentColor`, so `color` tints it):

```css
ul.bullet-svg.bullet-arrow li::before {
	-webkit-mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M508 256 336 428l-28-31 117-114-3-5H4v-45h421L305 113l31-29 172 172z'/></svg>") no-repeat center;
	mask:         url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M508 256 336 428l-28-31 117-114-3-5H4v-45h421L305 113l31-29 172 172z'/></svg>") no-repeat center;
}
```

Then write `<ul class="bullet bullet-svg bullet-arrow">…</ul>`.

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

**7. Mobile hero — `pos-x-mobile`, `image-mobile`, `stack`**

`[parallax]` takes `pos-x` (default `50%`) for the horizontal focal point of the background image. A wide hero crops hard on a phone, so the subject usually needs a different X there — set `pos-x-mobile` and leave `pos-x` alone:

```
[parallax name="home-page-hero" image="/wp-content/uploads/hero.webp" pos-x="50%" pos-x-mobile="75%" top-y="900" bottom-y="-300"]
```

It applies at ≤1024px (`window.mobileCutoff`). Don't set `object-position` inline on `.hero-bg`; it's in `style-grid.css` driven by the `--hero-x` / `--hero-x-m` custom properties so the mobile media query can win.

The **Y** value is not settable above the breakpoint — the parallax drift in `script-pages.js` recomputes it on every scroll from `top-y` / `bottom-y`, which is why a desktop hero's inline style reads e.g. `object-position: 50% 6%`. Only the X is yours. Below the breakpoint the drift is off entirely and the CSS value stands.

**Art-directed mobile crop — `image-mobile`**

`pos-x-mobile` only moves the focal point of the *same* file. When the desktop photo can't be cropped into a usable phone shape at all — a wide group shot, a landscape with the subject off to one side — hand it a second file:

```
[parallax image="/wp-content/uploads/hero.webp" image-mobile="/wp-content/uploads/hero-mobile.webp"]
```

It renders as `<picture><source media="(max-width:1024px)">` wrapped around the same `<img class="hero-bg">`, so the swap is the browser's decision and the cached HTML stays byte-identical for every device. The `<head>` preload splits into two media-scoped links so a phone only high-priority-fetches the file it will actually paint — a single unconditional preload would make every phone download both. `mobile-breakpoint="860"` moves the boundary. `--hero-ar-m` picks up the mobile file's real ratio so a contentless hero sizes its box to the crop it's actually showing.

**Copy below the photo instead of over it — `stack="true"`**

Overlay text stops working on a narrow screen: the text panel covers the subject and the photo degrades into a texture behind it. `stack="true"` takes the hero out of backdrop mode below the breakpoint — `.hero-bg` goes `position: static` at the top of the box and the `[layout]` content flows underneath it.

```
[parallax image="/wp-content/uploads/hero.webp" image-mobile="/wp-content/uploads/hero-mobile.webp" mobile-breakpoint="860" stack="true" padding="50"]
```

- **Opt-in per hero** — no existing hero changes. There is deliberately no site-wide or framework-wide default.
- No reordering needed: `buildParallax()` already emits the `<img>` before `$content`.
- Stacking uses the **same** boundary as the image swap. A media query can't read `mobile-breakpoint`, so the CSS ships one class per standard breakpoint (`hero-stack-576` / `-860` / `-1024` / `-1280`) and PHP snaps the attribute to the nearest. Never split the two — between them you'd have the phone-shaped crop being cover-fitted as a backdrop behind overlay text, which is worse than either mode.
- The gap between photo and copy is `--hero-pad` (the `padding` attribute). `padding-top` drops to `0` so the photo sits flush to the top of the section.
- `<picture>` is `display: contents`, so `.hero-bg` stays the direct grid child of `section.section` in both modes — without it the empty inline `<picture>` box would take a grid row of its own.
- **Site CSS usually needs a matching tweak.** A `.hero-text` styled as a scrim over a photo — translucent dark panel, white text, drop shadow — is wrong once it sits on the page background. Restyle it in `style-site.css` at the same breakpoint.

**8. `[parallax]` renders ONE markup path for every device**

`battleplan_buildParallax()` has no `is_mobile()` branch and must never get one back. EverCache keeps a single HTML entry per URL with no `Vary: User-Agent`, so whichever device warms the cache is served to all the others — a device-dependent hero means phones can hand desktop visitors an upscaled backdrop, or the reverse. See the `is_mobile()` notes in `functions.php`.

Everything that differs by device is a viewport rule in `style-grid.css`, fed by custom properties the shortcode emits on the section:

| property | from | drives |
|---|---|---|
| `--hero-h` | `height` | `min-height` above 1024px |
| `--hero-pad` | `padding` | top/bottom padding at ≤1024px only |
| `--hero-ar` | the attachment's real dimensions, falling back to `img-w`/`img-h` | `aspect-ratio` when the hero has no content (`.hero-empty`) |
| `--hero-ar-m` | the **mobile** attachment's real dimensions | `aspect-ratio` for `.hero-empty` and for the stacked photo at `stack="true"` |
| `--hero-x` / `--hero-x-m` | `pos-x` / `pos-x-mobile` | `object-position` |

Emit box metrics as custom properties, never as concrete inline declarations — an inline declaration outranks every stylesheet rule, so an inline `min-height` or `object-position` wins at all widths and no media query can take over.

Image size is not chosen in PHP. `srcset` + `sizes="100vw"` lets the browser pick on width *and* DPR; the `<head>` preload registers the same candidate set so the LCP fetch matches what the `<img>` resolves.

`.section-parallax-disabled` is retired — it only ever existed on the mobile branch. Hero sections carry `.hero-box` (box metrics) and, when they have an image, `.section-parallax` (layout + backdrop).

### Rendered HTML — what your CSS selectors must match

Write selectors against the HTML the shortcodes actually emit, not against the shortcode names. Verified output:

| Shortcode | Renders to |
|---|---|
| `[section name="x" style="2" width="full"]…[/section]` | `<section id="x" class="section style-2 section-full …">…</section>` — **content sits directly inside `<section>`; there is no inner wrapper.** `name` → `id` (spaces/underscores → hyphens). |
| `[layout grid="5e"]…[/layout]` | `<div class="flex grid-5e …">…</div>` — column widths come from the framework's `.grid-*` rules. |
| `[col class="c"]…[/col]` | `<div class="col c …"><div class="col-inner">…</div></div>` — **everything you put in a `[col]` lands inside `.col-inner`.** |
| `[nested grid="g"]` | `<div class="flex nested grid-g …">` — **deprecated 2026-08-02**, legacy content only; never use on a new build |
| `[txt class="c"]…[/txt]` | `<div class="block block-text span-100 c">…</div>` |
| `[group class="c"]…[/group]` | `<div class="block block-group span-100 c">…</div>` |
| `[btn class="c"]Label[/btn]` | `<div class="block block-button span-100 c"><a class="button c">Label</a></div>` — the class lands on **both** the wrapper div and the `<a>`. |
| `[img class="c"]` | `<div class="block block-image span-100 c">…</div>` |
| `[get-icon type="t"]` | `<span class="icon t"><svg class="icon-svg icon-t">…</svg></span>` — SVG fills via `currentColor`, so set `color` (not `fill`). |
| `[get-icon type="t" grid="1-2"]TXT[/get-icon]` | `<div class="icon-card"><div><span class="icon t">…</span></div><div>TXT</div></div>` — **one `.icon-card` per call.** To lay out a row of them, put each in its own `[col]` of a `[layout grid="Ne"]`; don't wrap them in a `<div>`. |

Practical consequences:
- Per-section styling hangs off `#section-name` (from `name`); reusable strip backgrounds off `.section.style-N`. There is **no** `.section.{name}` class.
- A component class on a `[col]` (`.service-card`, etc.) is a descendant of `.col-inner`, so `#services .service-card` works; `#services > .service-card` does not.
- Loose siblings (e.g. `[get-icon]` + a `<span>` label) are **separate** children — wrap each icon+label pair in its own `<div>` before laying a row out in a grid, or the grid splits icons from text.

**Trap — `.col-inner .block-button { order: 3 }` sends every `[btn]` to the end of its column.** That is right for a column whose button belongs under the copy, and wrong inside a hand-built row (`<div class="hero-btns">[btn]…[/btn]<div class="phone-number">…</div></div>`), where it silently reverses the source order — the phone button renders before the primary CTA. Reset `order: 0` on the buttons in any such row.

**Trap — `.section[class*="style-"] { overflow: hidden }` clips anything hung outside a styled band.** A decorative `::before` positioned above the section (a wave rising out of the band before it, a badge overhanging the top edge) is invisible the moment the section carries a `style="N"`. Paint the effect over the section's *own* edge instead — e.g. a wave in the NEXT band's colour laid across its own top — or drop the `style-N` and give the section its background by ID.

**Trap — `div.grid-1-1-1-1` collapses to two columns at 1280px and never collapses again.** There is no one-column rule for it at 860 or 576, so a four-up row of cards stays 2×2 on a phone. That is usually right for short cards and wrong for anything wordy; pin the count yourself per breakpoint (`#hero-trust .trust-card { grid-template-columns: 1fr }`) rather than assuming it stacks. An ID-scoped rule outranks the framework's `:not()` chain comfortably.

**Trap — `div.grid-1-1` (and `grid-3-2` / `grid-2-3`) stay two columns until 576px.** A photo + copy split is still side by side on a 700px tablet. Stack it yourself at 860 by ID (`#purpose .flex { grid-template-columns: 1fr }`).

**Trap — `[parallax]` defaults to `width="edge"`, so its `.flex` spans edge to edge.** `section.section-edge .flex` puts the hero copy flush against the screen edge. Pull it back into the content column with `#home-hero .flex { grid-column: default-start / default-end }`.

**Trap — every heading centres at ≤1024px.** `style.css` sets `.screen-mobile h1–h6 { --h-text-align: center }`. Copy under a heading stays left unless you centre it too, so decide per band: either centre the copy column at 1024 to match, or pin a heading left with an ID-scoped `--h-text-align: left` (1-0-1 beats 0-1-1). Headings that sit inside a card beside an icon, or in the footer, almost always want the pin.

**Trap — `[get-biz info="…" icon="phone"]` prints the icon OUTSIDE the `<a>`.** The icon is a sibling before the link. A "phone button" box, background or hover has to go on the wrapper (`.phone-btn`), not on `.phone-btn a`, or the icon sits outside the box.

**Trap — links underline by default.** Nothing in the framework sets `text-decoration: none` on `a` (`--link-decoration` is declared but never consumed). Footer lists, phone links and contact rows need an explicit `text-decoration: none`.

**Trap — `order: 0` on a `[btn]` can move it ABOVE the other blocks.** The `order: 3` reset above is only safe when every sibling is a button. In a card that stacks `[img]` → `[txt]` → `[btn]`, leave the button's order alone, or the link renders above the photo.

**Trap — `.flex.valign-stretch .block-button:last-child { align-self: end }` pushes a card's button to the RIGHT.** It exists to drop the last button to the bottom of an equal-height card in the default row layout. Make the card's `.col-inner` a column flexbox (`flex-direction: column` + `margin-top: auto` on the button, the usual "pin the button to the foot" move) and the cross axis becomes horizontal, so `align-self: end` now means right-aligned. Reset it on that button: `align-self: stretch` (then `text-align` on `.block-button` decides). Hit on Once Upon A Weekend's retreat cards.

**Trap — `body` carries `word-spacing: 0.1em`.** Fine for Open Sans; geometric sans faces (Jost, Futura-likes) read as gappy, almost justified. If body copy looks loose, reset `word-spacing: normal` on `body` in `style-site.css`.

**Verifying a build in headless Chrome: its window can't go narrower than ~500px.** `--window-size=400,…` still lays out at about 500 and crops the screenshot, so a phone render looks clipped on the right. Load the page in a `<iframe width="400">` inside a wider window instead.

**Trap — `[get-icon type="X"]` puts `X` on the element as a class**, so a component class that happens to share an icon type's name silently matches the icon too. A testimonial card with `[get-icon type="quote"]` next to `<p class="quote">` is the live case: `.review-card .quote { grid-column: 2 }` grabs the *icon* as well and drags it out of its column. Qualify the selector with the tag (`p.quote`, `span.icon`) whenever a component class collides with an icon slug — the icon list is in [functions-icons.php](functions-icons.php).

**Trap — `width` on `.icon` does NOT resize the icon.** [style.css](style.css) sizes the glyph as `.icon-svg { width: 1.15em; height: 1.15em }` and sets `.icon { display: inline-flex !important }`. So `.my-thing .icon { width: 34px }` resizes the *flex box*, centres the same-size SVG inside it, and looks like it did nothing. Size the SVG instead:

```css
.my-thing .icon .icon-svg { width: 34px; height: 34px }
```

Same `!important` bites layout: `.icon { display: block }` loses to the framework's `inline-flex !important`, so a quote mark you want on its own line above the text needs `display: block !important`.

**Trap — `.logo-img` carries `filter: none !important`.** [style-logo-slider.css](style-logo-slider.css) declares it, so any site rule tinting the brand marks (e.g. knocking them white for a dark strip) needs `!important` of its own *and* higher specificity.

And a design warning that comes with it: **`filter: brightness(0) invert(1)` is the wrong way to whiten the HVAC brand marks.** It works for a wordmark on transparent, but a plate-style logo (bryant, carrier, goodman — white text on a dark plate) inverts *plate and all* and renders as a solid white blob. For a dark brand strip, place the marks by hand using the `-white` variant in `common/hvac-{brand}/` where one exists (american-standard, york, trane, lennox…) and the brand colour where it doesn't (Honeywell and Bryant are red, which reads fine on black).

**Trap — a `background-clip: text` gradient stretches over the WHOLE heading, not per line.** A silver/gold bevel on an `h1` looks right on one line and renders line 2 in the gradient's dark stop — a grey second line that reads as a bug. Tile it to the line box:

```css
h1, h2 {
	background-image:  var(--headline-sheen);
	background-size:   100% calc(var(--h-line-height, 1.04) * 1em);
	background-repeat: repeat-y;
	-webkit-background-clip: text; background-clip: text;
	-webkit-text-fill-color: transparent;
}
```

This is the one legitimate `em` in site CSS (see the units rule): it resolves against the heading's **own** font-size, so a single declaration covers every heading size on the site, and it tracks any per-scope `--h-font-size` override for free. It needs the heading's leading to come from `--h-line-height` rather than a raw `line-height`, or the calc has nothing to read.

(Splitting each line into its own `<span>` also works, and is what the coupon example above does — prefer that when the line breaks are editorial anyway, since it also gives you a per-line colour.)
