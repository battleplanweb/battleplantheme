# Battle Plan Theme — Payments (PayPal)

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

## Payments — PayPal Pay in 4 (`includes/includes-paypal.php`)

A drop-in payment button. PayPal, debit/credit card, and **Pay Later ("Pay in 4")** — four
interest-free payments, which is the reason this exists. Same architecture as the Site Pulse
Store's Stripe checkout: **no SDK, no Composer**, just `wp_remote_request()` against PayPal's
REST API. The client's own PayPal account takes the money; card details never touch the server.

### Setup (per site)

```php
// wp-config.php — from the PayPal Developer dashboard, "Apps & Credentials"
define( 'PAYPAL_CLIENT_ID',     '...' );   // public — it ships in the page source
define( 'PAYPAL_CLIENT_SECRET', '...' );   // server-side only
// define( 'PAYPAL_SANDBOX', true );       // sandbox needs its OWN id/secret pair
```

The module is dormant on any site without those two constants. Also make a **thank-you page** at
`/payment-received/` (or point `redirect=""` elsewhere) — the capture redirects there with
`?payment={capture id}`.

### Getting the keys — the Sandbox/Live toggle is the whole trap

At [developer.paypal.com](https://developer.paypal.com/dashboard/applications) → **Apps &
Credentials** there is a **Sandbox | Live** toggle at the top of the page. It defaults to
**Sandbox**, and the two screens look identical — same layout, same "Client ID" and "Secret"
labels, keys of the same shape. Copying from the wrong one is the single most common way this
module fails, and the only symptom is `Client Authentication failed`, which reads like a typo.

**Log in as the business account that will receive the money** (the client's, not ours), then:

1. Click **Live** on the toggle.
2. Pick the app, or **Create App** (type: Merchant).
3. Copy the **Client ID**, and click **Show**/copy on the **Secret**.
4. Both values must come from the **same app** — easy to mix up with two apps open.
5. Paste into wp-config and make sure `PAYPAL_SANDBOX` is **absent or false**.

⚠️ **Generating a new secret immediately kills the previous one**, and the app page carries on
looking unchanged — so an old secret in wp-config is dead while appearing perfectly valid.

If the **Live** toggle is greyed out or Create App fails, the business account setup isn't
finished (email confirmation, business details, bank). No live keys exist until it is.

**Test in sandbox first.** Sandbox keys + `define( 'PAYPAL_SANDBOX', true );` exercises the whole
path — buttons, Pay Later, capture, receipts, the Payments record — with fake money. Sign in at
checkout with a sandbox *personal* test account from Testing Tools → Sandbox Accounts, never a
real PayPal login.

### `[paypal]`

```
[paypal amount="1250" item="Install deposit"]                 fixed price
[paypal item="Invoice payment" min="25" max="10000"]          customer types the amount
[paypal amount="99" item="Service call" fields="name,email,phone,note"]
```

| Attribute | Default | Notes |
|---|---|---|
| `amount` | *(open)* | Fixed price. Omit and the customer gets an amount field. |
| `item` | business name + " payment" | Shows on the PayPal screen and both receipts. |
| `min` / `max` | `1` / no cap | Bounds for an open amount. Enforced server-side. |
| `label` | `Amount to pay` | Wording above an open amount field. |
| `fields` | `name,email` | Any of `name,email,phone,note`. All required except `note`. |
| `paylater` | `true` | `false` drops the Pay in 4 button. |
| `card` | `true` | `false` leaves PayPal + Pay Later only. |
| `message` | `true` | The "4 interest-free payments of $x" banner above the buttons. |
| `redirect` | `/payment-received/` | Thank-you page. |
| `class` | — | Extra classes on the wrapper. |

Two `[paypal]` blocks on one page share a single SDK copy, so **the first one's `card`/`paylater`
settings win** — PayPal can't load two differently-configured SDKs on one document.

### ⚠️ The browser never sets the price

The shortcode's settings (amount, min, max, item, redirect) are emitted as base64 JSON with an
HMAC signed by `wp_salt('auth')`, and the REST endpoints refuse anything whose hash doesn't
match. A nonce would be wrong here — per-user and time-boxed makes the markup uncacheable and
breaks behind EverCache. Same trick `[bp-form]` uses for its spam check.

So: **never** move the amount out of the signed config, and never let a request parameter
override it. On a fixed-amount button whatever the browser sends is ignored outright.

### What happens after a payment

- A **Payments** record (`bp_payment` CPT, admin sidebar) with the amount, PayPal fee, net,
  funding method, transaction ids and whatever the form collected.
- Email to `customer_info()['email']` with all of the above, plus a receipt to the customer.
  PayPal sends its own receipt too — ours exists to carry the form fields, which PayPal's can't.
- `do_action( 'bp_paypal_paid', $details, $post_id )` for per-site follow-ups (CRM push, tag a
  lead, mark an invoice paid). Put those in `functions-site.php`.

`PENDING` is recorded as a real payment — PayPal holds some for review — and the customer is
told it isn't settled yet. Anything else is refused and nothing is written down.

### ⚠️ "Pay in 4 isn't showing" — it's almost never the code

The button renders perfectly, just without the Pay Later option on it. There's no error and
nothing in the console, so there's nothing to debug. In order of likelihood:

1. **The amount is outside $30–$1,500.** PayPal's published Pay in 4 range for the US. Outside
   it there is no offer to make, so neither the banner nor the button appears. This is the usual
   answer.
2. **Sandbox without `buyer-country`.** The SDK reads the buyer's country from their IP; a
   sandbox buyer hasn't got one, and a buyer of unknown country isn't eligible. The module sends
   `buyer-country=US` automatically whenever `PAYPAL_SANDBOX` is on — PayPal **rejects** that
   parameter in production, which is why it's gated rather than always sent.
3. **The merchant account isn't eligible.** Must be a US business account transacting in USD.
   Check PayPal dashboard → Pay Later.
4. **The buyer is in Missouri or Nevada.** Pay in 4 isn't offered there.

Logged-in admins see a note under the button covering 1 and 2 — see `bp_paypal_admin_hint()`.

Two things that are *not* the cause, so don't go changing them: `disable-funding=credit` does
**not** disable Pay Later (`credit` is PayPal Credit, the revolving line — a different product),
and `style.label="pay"` doesn't suppress it either. Pay Later does require
`style.layout="vertical"`, which is the module's default.

### ⚠️ Pay in 4 vs Pay Monthly — PayPal picks, you can't

Both live under the `paylater` funding source, and their ranges overlap:

| Offer | Range | Terms |
|---|---|---|
| **Pay in 4** | $30 – $1,500 | 4 interest-free payments |
| **Pay Monthly** | $199 – $10,000 | 6, 12 or 24 months |

Between **$199 and $1,500 both qualify**, and PayPal decides which one the banner promotes —
usually Pay Monthly. There is **no** offer-type parameter in the v5 messaging API; it doesn't
exist, so don't go looking for it again.

This is a *messaging* question, not an availability one: the buyer still gets Pay in 4 as a
choice inside PayPal's window at $800. To see Pay in 4 in the banner itself, test an amount
between **$30 and $198** — under Pay Monthly's floor, so it's the only offer that qualifies.

Also: the attribute is **`data-pp-pageType`** (value `checkout`), not `data-pp-placement`.
Older PayPal docs used `placement="payment"`; v5 replaced it and silently ignores the old name.

### Debugging a failed payment

Append **`?bp_paypal_debug=1`** to the page (or `define('BP_PAYPAL_DEBUG', true)`). Failures then
carry PayPal's own diagnosis — issue code, offending field, and the `debug_id` their support asks
for — onto the status line and into the browser console. Everything also goes to `error_log()`
unconditionally.

### Payments → PayPal Status (admin screen)

Mode and API host, a safe fingerprint of each constant (length + first/last 4 chars, never the
value), a live token test, and whether the thank-you page exists. "Re-check now" clears the
60-second cache and the stored access token.

**"Client Authentication failed" is PayPal's answer for every bad-credential case** — wrong keys,
truncated paste, leftover placeholder, or the other environment's keys — so the check retries
against the *opposite* host. Keys that fail on live and pass on sandbox aren't broken; they're
sandbox keys on a site that never set `PAYPAL_SANDBOX`. That's the usual cause and the one that
looks most like a typo.

⚠️ **Credentials are whitespace-stripped, not just trimmed** (`bp_paypal_clean_key()`). PayPal's
dashboard wraps a long credential across lines, and selecting it by dragging picks up the newline
*in the middle* of the value, where `trim()` can't see it. It goes into
`base64_encode("$id:$secret")`, the Basic auth header is silently wrong, and PayPal reports it as
the same "Client Authentication failed" you'd get for a genuinely bad key. Nothing legitimate is
lost — PayPal keys are base64url-ish and never contain whitespace. The status screen still
reports it, because it means wp-config doesn't say what it appears to say.

⚠️ **This belongs in wp-admin, not behind a front-end debug flag.** It was on the front end
first and that was wrong twice over: gated on `current_user_can()` it was invisible in the
logged-out window where you actually test a payment button, and ungating it would have printed a
fingerprint of the client *secret* to anyone who appended a query string.

⚠️ **These routes never return a 4xx/5xx.** Every failure is an HTTP **200** carrying an `error`
field — same convention as `bp_handle_form_submission()`. WP Engine's edge throws away an origin
5xx body and serves its own error page, so a "proper" status code means the customer sees a bare
`HTTP 502` and the real reason is gone. Don't change it back.

⚠️ **`current_user_can()` is not enough to gate this on.** A logged-in admin calling a REST route
from the front end is anonymous to WordPress unless the request carries an `X-WP-Nonce`, and
these routes deliberately have no nonce (it would make the page uncacheable). An admin-only check
on its own would silently never be true — hence the flag, mirroring `bp_form_debug()`.

The other half of this: PayPal's `onError` is a catch-all that fires for our own rethrown
`createOrder` failure too. It used to paint "Something went wrong at PayPal" straight over the
specific reason, hiding the answer. The `explained` flag in `script-paypal.js` is what stops that
— **don't remove it.**

### Filters

`bp_paypal_currency` (default `USD`) · `bp_paypal_redirect` (default `/payment-received/`) ·
`bp_paypal_buyer_country` (sandbox only, default `US`) · `bp_paypal_sdk_args` (last resort — the
whole SDK query string, for a parameter the module doesn't expose)
