---
target: Whole Site Pulse app
total_score: 21
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:G:\\Battle Plan Web\\00 - Battle Plan Assets\\theme\\GitHub\\battleplantheme\\pages\\page-site-pulse-dashboard.php"
target_fingerprint: "sha256:1f32db5c713ae41fa955d6bee505fdbadad0434aa2001d76a589c6f6cee8a9de"
target_path: "G:\\Battle Plan Web\\00 - Battle Plan Assets\\theme\\GitHub\\battleplantheme\\pages\\page-site-pulse-dashboard.php"
timestamp: 2026-10-27T12-25-29Z
slug: pages-page-site-pulse-dashboard-php
---
Method: dual-agent (A: design review · B: detector + live browser). Live target: myrovintest.wpengine.com/site-pulse-dashboard/ (Odin mode, viewing as Jay Bivin), 1440×900 and 390×844.

## Design Health Score
| # | Heuristic | Score | Key Issue |
|---|---|---|---|
| 1 | Visibility of System Status | 2 | Bell 99+ beside "No conversations yet"; Filter count vanishes on Completed tab with the filter still applied |
| 2 | Match System / Real World | 2 | Mileage speaks the user's language; elsewhere "Feeds Section A", "$0.6/mi", "Added by Reports" |
| 3 | User Control and Freedom | 3 | Sheets, Cancel, Reopen all present; reload restores last panel, URL never changes |
| 4 | Consistency and Standards | 1 | Bar slot count 3–5 by screen, filter above/below tabs, two empty-state styles, square vs round trash |
| 5 | Error Prevention | 2 | 62 native confirm(); one-tap trash per Settings → Reports row; no miles/$ preview before Save day |
| 6 | Recognition Rather Than Recall | 3 | Labeled icons, pre-title/title pattern; "Summarize", "Find New Reviews" need learning |
| 7 | Flexibility and Efficiency | 2 | Search, filters, voice quick-add; no shortcuts, no deep links, column-major 2-up lists |
| 8 | Aesthetic and Minimalist Design | 3 | Calm, disciplined palette; repetition ("GM Bi-Weekly Report" on every row, $360.91 twice) |
| 9 | Error Recovery | 1 | "Could not save." ×45 with no reason or retry; session-expiry alert() wall |
| 10 | Help and Documentation | 2 | Ask orb is real help; no inline hints on complex forms, no first-run guidance |
| **Total** | | **21/40** | **Acceptable** |

## Design Specificity Verdict
LLM: halfway authored. Mileage (route arrows, date tiles, start→stops→end timeline, voice quick-add) is built for a supervisor driving between restaurants. Most other screens are a well-kept SaaS admin kit in oxblood and gold: big H1, full-width search, tabs, Filter, cards on beige. The dashboard never mentions a store, shift or due report. Report view is a stack of identical ALL-CAPS blocks.
Deterministic: CLI 0 findings on the PHP and JS (regex-only on those file types; element rules don't run; not a clean bill). Live: 5–6 findings per normal view, 148 on Reviews, collapsing to a few systemic causes: muted text token #a3807a at 3.5:1 (search placeholders, "No comment, just a rating." ×22, "101 mentions"); a 10.8px small-label token (chart %, star labels, notification badge, god bar); owner-reply blocks flagged side-tab + nested-card ×42 each (one deliberate pattern); god bar 4.0–4.2:1 (god-only); single-font advisory.
Overlay: injection into the user's window was blocked by Chrome's local-network permission; no overlay visible.

## Priority Issues
1. [P1] Bottom bar: labels break mid-word ("Summariz/e", "Dashboar/d" at 390px) and slot count drifts (Mileage list 5, Reports/Settings 3). Fix: never break inside a word (hyphens:none, overflow-wrap:normal; ellipsis instead), shorter labels, always exactly 4 slots + overflow to More, fixed bar width. — polish/harden
2. [P1] Filter state lies: "Filter · 1" on Open, "Filter" on Completed with same store filter; filtered empty state says "You're all caught up". Fix: count from filter state not per tab; filtered empty state names the filter and offers Clear filter. — clarify
3. [P1] Report lists/view lack hierarchy: every row titled "GM Bi-Weekly Report", identical green Submitted chips, column-major order, "Show 16 more" above "34 older"; empty sections get full blocks with "—". Fix: row title = store, meta = date · GM; chip only for non-default status; row-major; collapse unanswered sections. — typeset + layout
4. [P2] Errors and destructive actions outside the system: 45× "Could not save.", 62 confirm(), session alert() (also fires on the bare login page), one-tap trash on Settings rows. Fix: sp- confirm sheet with consequence copy, error toasts with reason + Retry, delete inside Edit. — harden
5. [P2] Muted text token fails contrast app-wide (#a3807a 3.5:1) and small-label token is 10.8px. Fix: darken ink-3 to ≥4.5:1 on white and surface; floor small labels at 12px. — audit/polish

## Persona Red Flags
Casey (phone, busy restaurant): bar labels broken mid-word; Fill route wraps under the input at 390px; ~1,400px of stat cards before the first review to reply to; back/forward caps 38px, drawer X 28px; Sign Out first in the drawer.
Sam (keyboard/low vision): focus ring is raw 5px rgb(255,0,0) outside tokens; Add Day location/purpose selects unlabeled; placeholders and muted text 3.5:1; sentiment bars rely on green/red + ~10px labels.
Alex (power user): no deep links (URL fixed), no shortcuts, column-major lists, filter resets on reload, bar Search duplicates the in-page search.
GM at closing time: My Reports empty state says "Start one with the + button" (bar says Add Report, no + exists); bar offers "View Reports" on that same screen; submit ends in a 3-second toast with no receipt or created action items.

## Minor Observations
Date input shows two calendar icons; "Charge to" placeholder repeats its label; "$0.6/mi"; first note has no author; Action Items filter sheet ~870px wide for 4 selects on desktop; Odin bar not dimmed under sheet scrim; zero-value Overdue/Drafts numerals tinted orange; dashboard mileage (this month) vs Mileage screen (pay period) unlabeled periods; dashboard skeleton spinners plus a page-level ring at once; mobile DOM starts with repeated hidden "Home" text.

## Questions to Consider
- Mileage is best because it shows the work (routes). What is a GM report's "route" — a strip of the store's last 6 reports?
- Should the dashboard ever show a zero, or collapse to the one next obligation?
- Do the back/forward caps earn two of seven tap targets on every screen, when phones and browsers already have back?
