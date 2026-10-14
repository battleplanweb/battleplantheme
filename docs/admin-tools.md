# Battle Plan Theme — Admin tools (media replace, search/replace, AI alt text)

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

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

## Database Search & Replace (`functions-search-replace.php`)

An in-dashboard, serialize-safe **Search & Replace** across the whole database — rename a company, swap an old phone number, move a domain, everywhere it appears in one pass, without SSH or a plugin. Admin-only, capability-gated (`manage_options`), no settings, no module toggle — an always-available agency tool loaded from `functions.php` right after Media Replace (`if ( is_admin() )`). Lives at **Tools → Search & Replace** (`tools.php?page=bp-search-replace`).

### Why it's not just a SQL `REPLACE()`
`wp_options` / `wp_postmeta` (ACF, Site Pulse settings, widgets) hold PHP-serialized strings whose byte-length is encoded inline (`s:11:"Acme Paving";`). A raw SQL `REPLACE()` changes the text but not the length prefix and **silently corrupts the row**. So this tool does **not** run SQL replace — it **reuses the framework's own serialize/JSON-aware recursive replacer, `bp_mr_replace_content()`** (from `functions-media-replace.php`), which unserializes, replaces inside the live structure, and re-serializes safely. (Same engine the media link-rewriter uses — reuse, don't rebuild.)

### Safety model (baked in)
- **Dry-run first.** "Preview Changes" scans and reports match counts per table + before/after snippets, and **writes nothing**.
- **Commit is a separate, nonced step behind a confirmation checkbox**, and re-scans authoritatively (it does not trust the preview's numbers).
- **Case-sensitive, exact-substring** match (correct for names/domains). Search term must be ≥ `BP_SR_MIN_LEN` (2) chars; search === replace is rejected.
- **Table picker.** Safe core-content targets are checked by default (`posts` content/title/excerpt incl. Elements, `postmeta`, `options`, term names + descriptions). Comments/users/meta are opt-in. **Custom (non-core) tables are auto-discovered** from the schema (`SHOW TABLES` prefix-matched, single-PK, text columns only) and listed opt-in — so the Site Pulse custom tables can be included when needed.
- Every table/column identifier is whitelisted against the real DB (never user-supplied); every value is `LIKE`-filtered and `prepare()`d; commits bust the right caches (`clean_post_cache`, `alloptions`, `clean_term_cache`).

### Caveat
**Database only** — a string hardcoded in child-theme files (`style-site.css`, `functions-site.php`) is on the filesystem and is not touched (same caveat as Media Replace). For a one-off with SSH access, `wp search-replace --dry-run` does the same job from WP-CLI.

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
