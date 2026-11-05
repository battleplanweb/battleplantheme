# Editing live sites from Claude Code — `wp bp`

`includes/includes-cli.php` adds a `wp bp` WP-CLI command that reads and edits a page's body,
Page Top/Bottom and SEO fields. It only loads under WP-CLI (`defined('WP_CLI')`), so it
adds nothing to a normal page request.

Claude Code reaches it over WP Engine SSH with the local wrapper `~/bin/bp-wp` (Git Bash):

```bash
bp-wp <install> bp pages                       # every page: id, path, SEO title, desc length, noindex
bp-wp <install> bp get ac-repair               # JSON: content, top, bottom, seo{}, rendered{title,desc}
bp-wp <install> bp get ac-repair --field=bottom > bottom.html   # one raw value, for editing
bp-wp <install> bp seo ac-repair --title='AC Repair in [get-biz info="city"] %%sep%% %%sitename%%' --dry-run
bp-wp <install> bp seo ac-repair --desc=       # empty value clears the field
bp-wp <install> bp content ac-repair --field=bottom --dry-run < bottom.html
bp-wp <install> bp log                         # recent edits on this site
bp-wp <install> bp undo 7                      # put back what entry #7 replaced
bp-wp <install> bp purge ac-repair home        # purge URLs from WPE + Cloudflare
bp-wp --find lovejoy                           # domain → install name (needs ~/wpe-creds.txt)
```

`<post>` can be an ID, a slug, a path (`services/ac-repair`), a full URL, or `home`. Elements posts
(`site-header`, `widgets`, …) work too, for site-wide markup like a sidebar badge.

⚠️ Stored content often has Windows line endings. Git Bash `sed` strips the `\r` unless you pass
`-b`, which turns a one-line edit into a rewrite of every line. Edit fetched content with the Edit
tool or `sed -b`, and check the `bp content --dry-run` diff shows only the lines you meant to change.
The install name is the WP Engine install name (`{install}.ssh.wpengine.net`), not the domain.

## How to work with it

1. **Read first:** `bp get` before any edit. `rendered` shows the title and description
   Google actually sees: `%%vars%%` and shortcodes resolved, and the auto title when no
   override is set.
2. **Always `--dry-run` first**, show the person the diff and the `renders as:` lines, then
   run the same command without `--dry-run`.
3. **Content edits:** `bp get <post> --field=content|top|bottom > file`, edit the file, then
   `bp content <post> --field=… < file`. Never pass HTML as a command-line argument.
4. Every write is logged with its before/after (option `bp_cli_log`, last 100 entries), saves a
   revision when `post_content` changes, and purges that URL from the WP Engine and Cloudflare
   caches. `bp undo <n>` restores meta as well as content.

## Images: alt text in the same pass

Every time a page is scanned for its title and description, also run `bp images <post>`. It
loads the page as a visitor sees it (header, sidebar, sliders included) and lists every image whose alt
is **missing**, just the **filename** or attachment title ("Carrier Sidebar Logo"), **generic**
("IMG 2041", "logo", "2020"), or **short** (under 3 words or 20 characters).

```bash
bp-wp <install> bp images home                    # what needs work (add --all for every image)
bp-wp <install> bp alt 10678 --alt='Lovejoy HVAC service van parked outside a Lucas, TX home' --dry-run
bp-wp <install> bp alt 10678 --ai --post=home --dry-run   # let the framework's vision model draft it
bp-wp <install> bp alt 10678                      # library alt is fine: just push it to weak page tags
```

`bp alt` sets the **Media Library** alt (what sliders, featured images and the header print) and
pushes it into every stored `<img>` for that file across the site, including Page Top/Bottom and
Elements:
- tags with the `wp-image-{ID}` class are overwritten (the existing sync behaviour);
- hand-written tags (`[img]` wraps a raw `<img src>`) are matched by file URL at any size and
  replaced **only when their current alt is weak**, so alt text written for that spot is kept.

The dry run lists every page field it would rewrite. The save purges the whole site cache,
since library alt shows on every page. `bp undo` restores the library alt and every synced field.

Judging the list:
- `id` empty = not in the library (a theme file or an external image): `theme` rows get fixed in code.
- Look at the image before writing (download its `src` and view it). Describe what is visible,
  with service + city where it fits naturally (80–125 characters). Never keyword-stuff.
- Testimonial headshots whose alt is the person's name are fine. Leave them alone.
- Purely decorative images (stars, dividers) should be `alt=""` with `aria-hidden="true"` in the
  markup, not described.

## Whole fleet: `bp-fleet audit`

`wp bp audit` checks one site (read-only, JSON): every indexable page's title and description,
plus weak image alt counted once per site, so a sidebar logo isn't counted on every page. Page
issues: `title-auto` `title-long` `title-short` `title-dup` `desc-missing` `desc-long` `desc-short`
`pipe` `hardcoded-title` `hardcoded-desc`. `--format=table` gives a quick view.

`~/bin/bp-fleet audit` runs it on every install in `deploy-standard.yml` + `deploy-sensitive.yml`
(6 at a time; `--first 3`, `--only a,b`, `-P 8`) and writes `~/bp-fleet/audit-<timestamp>/`:
`summary.csv` (one row per site, most work first), `pages.csv`, `images.csv`, plus raw
`<install>.json`/`.err`. `bp-fleet report <dir>` rebuilds the CSVs.

Fixing is done in batches from `summary.csv`: the usual `bp get` → write → `--dry-run` → save
flow, a handful of sites per Claude Code session (subscription usage limits, not API cost).

## Rules the command enforces

- SEO separators are `%%sep%%`. A literal ` | ` is refused.
- Title and description get the same checks as the editor's AI writer
  (`bp_seo_ai_problems()`): length (title 45–60, desc 120–155), and hard-coded business name,
  city or phone where a `%%sitename%%` / `[get-biz]` token belongs. These are warnings, not
  errors: fix them before saving.
- Values are sanitized exactly as the editor meta box does (`sanitize_text_field` /
  `sanitize_textarea_field`), so `[get-biz …]` and `%%vars%%` are kept.
- Writes run as the `battleplanweb` user when it exists, with kses off, so framework markup
  (`[section]`, `<svg>`, inline scripts) is saved exactly as sent.

## Notes

- ⚠️ **Never call `ssh … "wp bp …"` by hand with quoted arguments.** WP Engine's SSH gateway
  re-parses the remote command line, so quoting/escaping is lost: a title gets split at its spaces,
  `&` backgrounds a job, and a trailing `--dry-run` can be dropped, which turns a preview into a save.
  `bp-wp` sends the args as base64 and rebuilds them on the server, which avoids all of that.

- `bp-wp` passes `--skip-plugins` (plugins making outbound HTTP calls can hang the session; the framework is a
  theme, so it still loads). Set `BP_WP_PLUGINS=1` if a page's shortcodes need a plugin to render
  in `rendered`.
- Field map: body = `post_content`; Page Top/Bottom = postmeta `page-top_text` / `page-bottom_text`;
  SEO = `_bp_seo_title`, `_bp_seo_desc`, `_bp_seo_og_title`, `_bp_seo_og_desc`, `_bp_seo_og_image`,
  `_bp_seo_canonical`, `_bp_seo_robots_noindex`, `_bp_seo_robots_nofollow`.
- Only works on WP Engine installs (SSH + WP-CLI). The SSH key/config is described in the fleet
  ops notes (`~/.ssh/config`, `Host *.ssh.wpengine.net`).
