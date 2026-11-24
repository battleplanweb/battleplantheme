# Battle Plan Theme — Events, Booking & the one calendar

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

## The three setups

| Site has | Calendar shows | How it's switched on |
|---|---|---|
| Events only | events, exactly as before | `event_calendar` option (works with or without Site Pulse) |
| Events + Booking | events + bookable classes; a bookable event shows ONCE, with its button | Site Pulse modules **Events** + **Booking** |
| Booking only | bookable classes on the same calendar UI | Site Pulse module **Booking** (no Events CPT) |

`bp_events_on()` = the Events CPT is loaded (option OR the Site Pulse Events module). `bp_calendar_on()` = Events OR Booking — it gates `script-events.js`, `style-events.css` and the `/calendar/` universal page. Never gate calendar things on `get_option('event_calendar')` directly.

## Events as a Site Pulse module
- `includes/includes-site-pulse-events.php` — list/get/save/trash/upload AJAX; caps `view_events` / `manage_events`. Auto-enabled where `event_calendar` is installed (`sp_events_migrate_enable`).
- **One save path: `sp_events_save( $data, $id, $author )`.** It writes the ACF fields BY KEY (so the date-blocks / recurrence field types sanitise), then runs `bp_event_regenerate()` + `bp_event_sync_span()` — `wp_update_post()` alone never fires `acf/save_post`. Fires `sp_events_saved`.
- It always writes `field_bp_event_dates` (empty for repeating events). Without ACF's `_event_dates` reference meta, `get_field('event_dates')` returns the raw JSON string; `bp_event_date_blocks()` now decodes that too, but keep writing the reference.

## Booking sessions (what the calendar books)
Only **session-mode** services are on the calendar. A session row = one date/time with `capacity` / `seats_taken`.
- `includes/includes-site-pulse-booking-sessions.php` — creates sessions: one-off (`sp_book_add_session`) and weekly (`recurrences` rows rolled forward daily by `sp_book_maybe_roll_sessions`, horizon = the service's "Book up to"). UI: Settings → Booking → a session service → **Dates**.
- Per-service options live in `services.meta` via `sp_book_service_opts()`: `book_label` (default "Book Now"), `full_label` (default "Class Full"), `taxable`, `event_id`.
- Removing a date with seats taken is refused (or, from an event sync, the session is set `cancelled` and its bookings are KEPT + staff emailed). Never delete a booked session.

## Site Pulse screens (2026-11-21, modelled on Vagaro / SimplyBook)
Built like Store. `includes/includes-site-pulse-booking-types.php`. In the UI a service row is a **Service** and a type is a **Template** (code still says offering / type).
- **Booking → Calendar** (the appointment book; panel `booking-calendar`), **Booking → Services** (`booking-offerings`; Add picks a template first), **Booking → Staff & Hours** (`booking-staff`: the Staff & Resources, Working Hours and Check What Customers See cards, moved out of Settings), **Booking → Memberships**.
- **Customers:** with the Store on, booking people are merged into **E-Commerce → Customers** (`sp_store_customer_people` / `sp_store_customer_detail` filters in includes-site-pulse-store-members.php; free RSVPs never make an order). Booking → Customers (`booking-customers`) only exists on a site WITHOUT the Store.
- **Kinds** (`sp_book_type_kinds()`): appointment (exact start time), **call** (Service call: arrival window at the customer's address — `sp_book_kind_force()` fixes `slot_mode` = window, `requires_address` = 1, travel buffer on), class, series, rental. The kind decides which fields the form shows and their words (`SP_BOOK_FIELD_TEXT` in script-site-pulse.js); a service without a template picks a kind itself (`kind` param).
- **The form:** basics for the shape → Price & Payment (deposit only when "A deposit", tax only when paying) → Who Does It → When People Can Book → **More settings** (collapsed: group seats for appointments, at-the-customer's-address, who gets the job / out-of-area, class button wording). Every field explains itself behind an ⓘ. `cancel_window_min` is kept but hidden: nothing reads it until customers can cancel online.
- Planned next (agreed with Glendon): a customer-facing booking page (service → staff/anyone → time/window → details → pay), then self-service cancel/reschedule.
- **Settings → Booking → Service Templates**: the templates. Table `types` (booking DB 1.10): `name`, `kind` (`class` | `series` | `appointment` | `rental`, fixing mode + series), `rules` (JSON of the inheritable values, normalised by `sp_book_rules_normalize()` in service-column units). Starters seeded once per site (`sp_book_types_seeded`): Class (single), Class (multiple), Appointment (one-on-one), House Call (service call) — renamed 2026-11-21; sites seeded with the old names are renamed once (`sp_book_types_renamed_v2`), only where the name is still the original. Existing services were put on the matching starter with every differing key as an override, so nothing changed value. Editable and deletable; deleting a type leaves its offerings standalone (`type_id` 0) with their current values. A type in use can't change kind.
- **LIVE-LINKED**: `services.type_id` + `services.meta.overrides` (rule keys the offering set itself). Saving an offering: a rule equal to the type's value follows the type, a different one becomes an override; rules the form doesn't send keep their state. Saving a type pushes its rules into every follower's non-overridden keys (`sp_book_type_apply`) — materialised on write; the engine still reads plain service columns. The form marks changed rules "Custom · reset". Pushing seats doesn't change seats on dates that already exist (same as editing the offering).
- An offering with dates or sign-ups can't switch to a type of another kind.
- **Event pages are made in Events** (changed 2026-11-21): create the event in Events and pick the offering in its **Booking** dropdown. The offering form only says which event links to it; there's no website-page section there, and linked events stay in the Events list. (`sp_book_offering_set_page()` / the `page` param of save_service still exist but the form no longer sends it.) Offering Duplicate still copies a linked event (`sp_book_offering_duplicate`).

## Bookable events (linked)
`includes/includes-site-pulse-booking-events.php` (see its header). An event links to one session booking (`_bp_book_service_id` ↔ `services.meta.event_id`); the BOOKING owns the dates and they're copied onto the event (`sp_book_event_pull_dates`). Linking is done from the offering (above) or the wp-admin Booking dropdown. (The older model — hidden per-event services built from the event's own Bookable fields — was retired 2026-11-20.)

### How people book: each date vs all dates together (series)
`book_mode` on the event (`each` default | `series`) → `services.meta.series` → `sp_book_service_opts()['series']`.
- **each**: every date is booked and paid on its own (drop-in).
- **series**: one price buys a seat in EVERY date (a 4-week class). Decided 2026-11-20: **no late joiners** (enrollment closes once the first date starts), **no drop-ins**, **no partial refunds** (a full refund cancels every date; staff refund by hand otherwise). Each run of a class is its own event — the Site Pulse editor's **Duplicate** button starts the next run (opens as a draft; `copy_of` lets it keep the photo).
- An enrollment = **one booking per date** tied by `bookings.group_id` (booking DB 1.9; every row carries the lead's id; the lead = the first date's booking and carries the price). `sp_book_claim()` delegates to `sp_book_claim_series()` for a series service, so website reserve, Store checkout and staff New Booking all enroll every date, all or nothing. `sp_book_confirm()` / `sp_book_release()` on the LEAD cascade to every date; a member date can still be cancelled alone (a missed class).
- `sp_book_series_state($service)` = upcoming dates, `started`, `left` (tightest date), `lead`. `sp_book_public_session()` answers for the whole series whichever date was asked (id = lead, `series` = date count, `dates`, `closed`); the seats endpoint does the same; the cart always stores the lead session (one line per class).
- Website: the event page lists the dates with ONE Enroll button and "per person for all N classes"; calendar dates say "Class 2 of 4" and every button books the series; after the start, no buttons and "enrollment is closed".
- Notifications: one confirmation (`{dates_line}` lists every date — in the default confirmation, cancellation and staff-alert copy), a reminder before EACH date (queued on that date's own booking), the follow-up after the last.
- Not handled: dates added to a series after people enrolled don't get their seats; rescheduling an enrollment as a whole.

## Public side
`includes/includes-site-pulse-booking-public.php` + `js/script-booking.js` (+ styles at the end of `style-events.css`).
- Calendar entries come through two filters in `battleplan_createEventLog()`: `bp_event_log_block_html` (a bookable event's button, first day of each block only) and `bp_event_log` (one entry per DAY holding every class that day, one chip per class). Booking-only sites get the `[get-event-calendar]` shortcode + eventLog printer from this file.
- Event date line (`elements/element-events-meta.php`, used by `[event_teasers]`, the archive and event pages): a weekly repeating event reads "Tuesdays, from January 12th to February 9th, 2027" (or "…, starting {date}" with no end), using the rule's start and until. Three or more single-day date blocks, each 7 days apart and all at the same time, are shown the same way. Monthly and yearly rules still show only the next date.
- Event pages: `template-parts/content-events.php` builds content with `get_the_content()` — `the_content` NEVER runs there. Use its `bp_event_page_after_content` filter.
- Calendar shell (2026-11-21 redesign): `bp_calendar_markup()` in functions.php, shared by Events and Booking-only sites. It has a toolbar with prev/next arrows, the month title and Today (hidden on the current month). The grid has whole weeks, with the neighbouring months' days muted, and each day's entries render as chips in `.day > .events`; entries are no longer full-cell layers. Phones (≤860) show a dot per event day, and the tapped day's entries are copied into `.calendar-agenda` (still inside `#calendar`, so `#calendar …` booking handlers work). Swipe changes month. `abbr_days` is ignored now: day names are always 3 letters, with the full name in `<abbr title>`. Colours come from `--calendar-accent` (default `--button-background`).
- Calendar | List switch: `bp_calendar_views( 'calendar'|'list' )`, drawn only when BOTH a published /calendar/ page and the events archive exist. Event pages show a single "All Events" button (to the calendar, else the list). The list (archive-events.php) has no month headings; it is sorted by `bp_event_next_date()` (date blocks, not the legacy `start_date` meta) and each card gets an `.event-date-badge` (month + day) on its photo.
- Seats are fetched live after load (`bp_book_seats`, nopriv) — never trust a seat count in cached HTML. `script-events.js` fires `bp:calendar-rendered` after each month is drawn.
- Free (no payment) sessions: the dialog reserves directly (`bp_book_reserve`: honeypot `website`, 8/hour/IP). Paid sessions go to the Store cart.

## Paid bookings = a second kind of Store line
`includes/includes-site-pulse-store-bookings.php`. Paid booking REQUIRES the Store module (its checkout + payment provider).
- `cart_items` / `order_items` carry `kind` ('product' | 'booking'), `session_id` (+ `booking_id` on order lines). Booking lines have `variant_id = 0`; the cart key is `UNIQUE (cart_id, variant_id, session_id)` (store DB 1.7 dropped the old `cart_variant` key).
- Anything that loops lines for STOCK must skip `variant_id 0` / `kind != 'product'`. Booking lines carry their own `taxable` flag (`sp_store_quote` honours it).
- Lifecycle: place order → `sp_store_bookings_hold()` claims seats as PENDING bookings (125-min hold) → `sp_store_order_paid` (pri 4) confirms + queues the booking confirmation → `sp_store_order_cancelled` releases → `sp_store_refunds_recounted` with status `refunded` cancels the bookings. Those two hooks were added for this; fire them, don't bypass them.
- Drawer: booking lines are `data-line="{line id}"`; qty/remove endpoints take `line_id`. The dialog adds via the `store:add-booking` CustomEvent.
