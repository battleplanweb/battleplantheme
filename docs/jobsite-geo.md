# Battle Plan Theme — Jobsite GEO system

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

## Jobsite GEO System

A custom system for service businesses to publish real job entries that automatically generate SEO landing pages by service type and location.

### What It Does
Each jobsite entry (a real completed job) automatically:
1. Creates a taxonomy term for the city/state (`jobsite_geo-service-areas`)
2. Creates a taxonomy term for service type + location (`jobsite_geo-services`) — driven by AI classification
3. Records who added the job as the post author (there is no `jobsite_geo-techs` taxonomy — older notes that mention one are wrong)
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

> ⚠️ **`default_service` is required, not optional.** It is the safety-net fallback in `bp_geo_run_ai_rewrite()`: the service-type tag **and** the `/service/` landing page are only created when the resolved service category is non-empty. If the AI returns no `service_category` **and** `default_service` is blank, the post is orphaned with only its deterministic service-area tag — no service-type, no page, no archive intro. Set it to the client's primary service (e.g. `'Gutter Installation'`) on every new site. Also confirm `BP_ANTHROPIC_API_KEY` is defined in that site's `wp-config.php` — without it the whole AI classification step short-circuits and you get the same orphaned result.

> ✅ **New-site smoke test (do this once per install):** submit one test job **with a photo**, then confirm the full chain end-to-end: (1) a service-type term + `/service/{service--city-st}/` page were created, (2) the archive intro rendered, (3) the photo **shows on the front end** (not just under "Attachments"), (4) the photo got AI alt text. This single test exercises every silent failure mode (`default_service`, intro trigger, photo→ACF bridge, alt cron). A standing **"Jobsite GEO health"** admin notice (on the jobsite list + dashboard) also flags these three drifts automatically — but run the smoke test on setup so a new site is right from day one.

### One save path: `bp_geo_save_jobsite()`
The GEO app (`bp_geo_submit`) and Site Pulse's **Jobsites** module both create/edit jobsites through
`bp_geo_save_jobsite( $data, $post_id = 0, $author = 0 )` in `includes-jobsite-geo.php`. **Any new way of
adding jobsites should call it too** — don't hand-roll `wp_insert_post` + `update_field`. It:
- holds the save_post hooks off with `$GLOBALS['bp_jobsite_setup_running']` (from admin-ajax,
  `battleplan_saveJobsite` never fires and `bp_geo_validate_required_fields` would redirect + `exit`
  mid-JSON), then calls `bp_jobsite_setup()` itself once the fields exist;
- parents unattached photo uploads to the post and files them under the "Jobsite GEO" image category,
  and deletes photos that fall out of every slot (same rule as the editor);
- runs the 48-hour duplicate guard on new posts (held as a draft + email);
- **queues** the AI rewrite (`bp_geo_ai_rewrite_cron` + `spawn_cron()`) instead of running it inline —
  on first publish, on an explicit re-run (`ai`), or when the city/state changes.

The Site Pulse **Jobsites** module (`includes-site-pulse-jobsites.php`, caps `view_jobsites` /
`add_jobsites` = add + edit own / `manage_jobsites` = everyone's + publish held drafts) is switched on
automatically on any install where Jobsite GEO is installed.

### Duplicate Guard (GEO app submissions)
A jobsite is the worst network a phone ever sees, and the write can land while the response
never makes it back — the tech sees "Submission failed", taps Submit again, and the site gets
`debbie-rollins` **and** `debbie-rollins-2`.

`bp_geo_submit()` checks for an existing `jobsite_geo` whose **normalized title**
(`bp_match_key_from_title()`) matches, created **within 48 hours**, in any status. If one exists:
- the new post is **left as a draft** (never published, so no duplicate `/service/` page)
- `bp_geo_duplicate_of` post meta records the earlier post's ID
- `emailMe()` sends a side-by-side comparison of both entries with edit links

Publishing the held draft by hand runs the normal first-publish AI rewrite + taxonomy assignment,
so a genuine repeat visit costs one button press. Only the app endpoint is guarded — the
Company Cam / HCP / Workiz importers dedupe on their own external job IDs.

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
- `bp_jobsite_geo` — field technician; can create/edit own jobsite *drafts* in wp-admin (no publish, no editing once live)
- `bp_jobsite_geo_mgr` — manager; broader access
- These WP roles only govern wp-admin. The GEO app accepts any logged-in WP user, and Site Pulse uses its
  own caps (`view_jobsites` / `add_jobsites` / `manage_jobsites`).

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
