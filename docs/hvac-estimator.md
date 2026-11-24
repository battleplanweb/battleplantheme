# Battle Plan Theme — HVAC Replacement Estimator (`[hvac-estimator]`)

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

A homeowner answers up to six questions and sees an installed price range plus "As low as $X/month",
with **no contact details required**. Afterwards they can request a free in-home estimate (name, phone,
email) or email themselves the estimate (name, email). The office is emailed on **every** completed quiz.

| File | What |
|---|---|
| `includes/includes-hvac-estimator.php` | Config defaults, question flow, pricing engine, REST endpoints, mail, completion log, shortcode, Tools page |
| `js/script-hvac-estimator.js` (+ `.min.js`) | Front end. Walks the flow data the server provides. **No pricing rules in JS.** |
| `style-hvac-estimator.css` | Scoped to `.estimator`. Inlined in `<head>` (wp_head 10) only on a page whose content or postmeta holds the shortcode, **before** style-site.css (11), so site overrides win |

## Turning it on for a site

1. Install it the same way as Site Pulse or Jobsite GEO. In the site's `functions-site.php`, inside
   `battleplan_updateSiteOptions()`, add:

   ```php
   update_option( 'hvac_estimator', array (
       'install'      => 'true',
       'company'      => $customer_info['name'],
       'phone'        => $customer_info['area'].'-'.$customer_info['phone'],
       'office_email' => $customer_info['email'],
       'ref_prefix'   => 'HTC',
       'financing'    => array ( 'provider' => '…', 'disclosure' => '…' ),
       'tiers'        => array ( 'good' => array ( 'label' => 'Good', 'desc' => 'Single-stage', 'series' => '…' ), … ),
       'prices'       => array ( 'gas' => array ( '2' => array ( 'good' => array ( 10700, 12100 ), … ), … ), 'heatpump' => … ),
   ) );
   ```

   Every key you leave out keeps its framework default (`bp_est_defaults()`). `copy` and `financing`
   **merge**, so `'copy' => array ( 'sqft_hint' => '…' )` changes only that one sentence. Every other key
   **replaces** the default whole. Home Town Comfort Solutions' block is the full reference copy.
   Like every site option, it reaches the database only when `battleplan_updateSiteOptions()` runs: hit
   **Clear Cache** in the wp-admin footer after deploying, or it waits for the nightly Chron B.
   (Code-level alternative: the `bp_hvac_estimator_config` filter runs after the option is merged, and also turns the module on.)
2. Create a page and put `[hvac-estimator]` in it. It needs no other markup.
3. Brand it from `style-site.css` with tokens on `.estimator`: `--estimator-accent` (bars, borders, primary
   button), `--estimator-line`, `--estimator-text`, `--estimator-surface`, `--estimator-warning`.
   Headings, buttons, radios and contact fields already use the site's own styles.
4. There is no live/preview switch: the tool works wherever the shortcode is. To test before launch, put it
   on an unlinked, noindexed page. Launch by adding it to the real page / menu.

## Config keys (`bp_est_defaults()` documents all of them)

| Key | Meaning |
|---|---|
| `prices[type][tons][tier] = [low, high]` | Installed price, whole dollars. A missing cell is never guessed: the homeowner gets the free in-home estimate outcome and the office email names the missing cell. |
| `tiers[key] = label, desc, series` | Comfort-level answers, e.g. Better / Two-stage / Gold 17. |
| `size_substitutions[tier][tons] = tons` | Price a size the series isn't made in at another size (Better/Best 2.5 → 3, 3.5 → 4). The office email shows both sizes. |
| `sizes` | Square-footage bands → estimate tons. `load_calc => true` adds the "most homes this size need two systems" line. |
| Product photo (automatic) | `bp_est_product()` finds the site's own `products` post whose title contains the tier `series` as a whole phrase, within `system_types[type].product_type` (gas → `air-conditioners`, heat pump → `heat-pumps`), and shows its featured image beside the price, linked to the product page. No match or no photo → no picture. Pin one with `tiers[tier]['product'][type] = post ID`. `'product' => false` turns it off for that tier. Cached 12h, cleared when a product is saved. |
| `system_types[key].priced` | `false` (Not sure) ends the quiz at the free in-home estimate outcome. |
| `three_plus` | `'call'`: 3+ systems ends at call-only. `'ask'`: asks "Just one / More than one", and Just one gets priced. |
| `payment_factor_bp` | Monthly = price × bp / 10000. `125` = 1.25%. |
| `financing.disclosure`, `financing.provider` | Shown next to every monthly figure and in the homeowner email. |
| `copy[...]` | Every sentence the homeowner sees (hint, load-calc line, rough-estimate note, outcome titles, CTAs). |
| `phone`, `office_email`, `company`, `ref_prefix` | Defaults come from `customer_info`. |

## Rules that are deliberate

- **One implementation.** `bp_est_walk()` validates a path and `bp_est_compute()` produces the result.
  The UI, the homeowner email and both office emails all read that result. The flow rules (`ask_if`,
  `end_if`, `depends_on`, `choices_by` in `bp_est_questions()`) are sent to the JS as data.
- **Monthly rounding** is integer math, to the nearest dollar, with exact halves going to the even dollar
  (11,400 → $142, 14,200 → $178). The headline uses the low end of the range.
- **Outcomes:** `priced`, `call` (more than one system: never add two ranges together), and `inhome`
  (system type unknown, or a price is missing).
- **Completion reference** = HMAC(browser session id + answers), e.g. `HTC-PW8F2YQ7`. It is stable across
  refresh, retry and reopen, so the `wp_bp_estimates` row (UNIQUE ref) dedupes the office email.
  Changing an answer makes a new reference, because that is a different completed quiz.
- **The office email is sent after the reply** (shutdown + `fastcgi_finish_request`), so the result never
  waits on the mail server, and a mail failure can't hide it. A failed send is recorded on the row,
  `error_log`ged, and flagged with a wp-admin notice. It is retried when the homeowner reopens the result
  (5 tries max) or from the Resend button on Tools → Price Estimator.
- **Contact actions are sent inline**, so the homeowner sees a true success or failure. A failure keeps
  their typed details for a retry. A repeat of a successful send within 15 minutes is not sent again. The
  estimate request says the office will call. It never claims an appointment was booked.
- **Protection without nonces** (the page is cached): contact posts must carry the server-signed
  `t`/`sig` from the quote response, be at least 3 seconds old, and pass a honeypot. Per-IP limits: 60
  quotes/hr, 20 office emails/hr (300/day site-wide), 6 contact posts/hr per action. Past the email caps
  the result still shows and the row says `limited`. Names matching `bp_blocklist_kill()` are rerouted
  to email@bp-webdev.com, as the forms do.
- **Not** the Housecall Pro widget: it can't carry the quiz context, so the in-home request is an office
  email with that context.

## Testing

Logic: `bp_est_walk()` / `bp_est_compute()` / `bp_est_monthly()` are pure. Run them with php-wasm and a
stub-WP harness. End to end: WP Playground plus a sandbox mu-plugin that captures `pre_wp_mail` and can
simulate a failure (see `reference_wp_playground_testing`).
