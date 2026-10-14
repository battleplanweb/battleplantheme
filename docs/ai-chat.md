# Battle Plan Theme — AI Chat + SMS handoff

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

## AI Chat + SMS handoff (Twilio A2P) — "never miss a lead" module

A website chat widget where a Claude-powered assistant helps visitors, captures leads, and (with explicit opt-in) moves the conversation to SMS so the contractor can take over from where the AI left off. Framework-native; **per-client Twilio number + A2P 10DLC brand** (their phone + legal identity). Shared Anthropic key.

### Files
`includes/includes-ai-chat.php` (config, REST `/message` + `/consent`, Claude call, tools) · `includes/includes-ai-chat-sms.php` (DB tables, inbound webhook `/sms-inbound`, human-takeover relay) · `includes/includes-ai-chat-reviews.php` · `prompts/prompts-ai-chat.php` (system prompt + industry blocks) · `js/script-ai-chat.js` · `style-ai-chat.css`. Loaded when `bp_module_on($ai_chat)`.

### Turning it on — `ai_chat` option (in `functions-site.php`)
```php
update_option( 'ai_chat', array(
    'install'        => 'true',
    'vertical'       => '',            // hvac|plumbing|roofing|electrical|'' (generic); '' = generic block, put specifics in the knowledge file
    'model'          => 'opus',        // 'opus' (best, recommended for live leads) or 'haiku'
    'greeting'       => '{greeting}. Thanks for stopping by …',  // OPENING BUBBLE. {greeting} → time-of-day salutation (client-side)
    'launcher'       => 'Chat with us',
    'contractor_sms' => '+1XXXXXXXXXX', // legacy single number (still works as the fallback for both roles)
    // Split routing (optional, per site): notification can be many, reply is ONE line
    // 'contractor_sms_notification' => '+1XXXXXXXXXX, +1YYYYYYYYYY', // comma-separated; ALL get lead alerts + the one-time handoff notice
    // 'contractor_sms_reply'        => '+1XXXXXXXXXX',               // the SINGLE line that two-way texts the customer (takeover relay); only this number's inbound texts relay
    'twilio_sid'     => 'AC…',          // Account SID — SAME across all clients under the one ISV account
    'twilio_number'  => '+1XXXXXXXXXX', // the client's own local number (differs per client)
    // 'twilio_messaging_sid' => 'MG…', // OPTIONAL per client: send via the Messaging Service instead of the bare number — attributes traffic to the approved A2P campaign (better deliverability, Twilio's recommended path). Get it at Messaging > Services > [service]. Omit = send From the number (legacy).
    'privacy_url'    => '/privacy-policy/',
    // 'terms_url', 'text_consent' (consent-card override) optional
) );
```
- **wp-config.php** (per site): `define('BP_TWILIO_TOKEN', '…')` (secret — same token as every client under the ISV account) and an Anthropic key (`ANTHROPIC_API_KEY`/`BP_ANTHROPIC_API_KEY`). Optional `BP_CHAT_WEBHOOK_URL` if Cloudflare rewrites break Twilio signature validation (403 on inbound).
- **Render gate:** the bubble only appears when `bp_chat_ready()` passes = Anthropic key **+** Twilio sid/token/number **+** `contractor_sms`. Missing `BP_TWILIO_TOKEN` or the Anthropic key = no bubble (the #1 "why isn't it showing" cause).
- **Knowledge base:** `ai-chat-knowledge.md` in the **child-theme root** auto-loads by convention (no option needed). `company_knowledge` is INLINE TEXT (never a filename); the file-path key is `knowledge_file`.
- **Greeting is config, not AI:** the opening bubble is `ai_chat['greeting']` localized into the page — the knowledge file only shapes the AI's *replies*. Tone rules (no emojis, direct) go in the knowledge file; the opener goes in `greeting`.

### Opt-in / consent flow (A2P-compliant, deterministic)
AI collects name + email + mobile, then a fixed **consent card** (Yes/No) shows the disclosure (brand, freq varies, msg&data rates, STOP/HELP) + Privacy/Terms links. **Only "Yes" sends a text**; consent audit (`consent_at`/`consent_ip`) is recorded first. Firing is deterministic — a safety net auto-shows the card after any lead with a valid mobile (`bp_chat_offer_text_consent`), so it never depends on the model remembering.

### Human-takeover relay (SMS)
On Yes, the contractor gets a text with the chat transcript; the **AI keeps covering the customer until the contractor's first reply**, then goes silent (`human_at`). Customer texts forward to the contractor prefixed with their name; the contractor replies to the Twilio number and it relays to the "active" customer (`bp_chat_active_conv`, a per-site option). Single number = one live customer at a time (dedicated per-customer numbers is the planned upgrade). Set the **inbound webhook** to `https://{domain}/wp-json/bp-chat/v1/sms-inbound`.

### Twilio A2P 10DLC onboarding (per client) — current console menu
Everything nests under **one ISV account** (yours): Customer Profile → **Brand** → **Campaign** → **Messaging Service** (+ number + webhook). Account SID + Auth Token are shared; only the number differs per client.
1. **Register the Brand** — menu path **Trust Hub → Registrations → Create A2P Brand** (the console search bar is useless here — it only returns docs, never the console page; navigate the left menu). Choose "create a new Brand" + "create a new compliance profile" (each client LLC is its own legal entity — never reuse yours). LLCs → **Standard** brand (needs EIN); no-EIN/low-volume → Sole Proprietor.
2. **Campaign:** use case **Customer Care**; opt-in type **Web Form**; paste the opt-in description + the public **Google Doc of opt-in screenshots** (Twilio can't upload images — the Doc link goes in the Message Flow field) + Privacy/Terms URLs; **sample messages** must include the brand name, STOP, and "msg & data rates may apply"; **opt-in confirmation message** = the opening SMS; **no opt-in keywords** (web-form opt-in). Twilio's generic "unable to verify" pre-check is advisory — submitting is fine when it flags nothing specific.
3. **Number + Messaging Service:** the menu item is **Numbers & senders** (Twilio renamed "Phone Numbers") → **Overview** → **Buy a number** (US, area code, SMS-capable) — or buy it inside **Messaging → Services → [service] → Sender Pool → Add Senders**. Create one **Messaging Service per client**, add the number to its **Sender Pool**, link the campaign.
4. **Inbound webhook:** **Messaging → Services → [service] → (Settings) → Send a webhook**, HTTP **POST**, URL `https://{domain}/wp-json/bp-chat/v1/sms-inbound`.

The reusable screenshot Doc + full A2P notes live in the auto-memory (`reference_a2p_optin_screenshots_doc`). Texts are carrier-filtered until the campaign is **approved**; pre-approval, test SMS to a **Verified Caller ID**.

### Deploy gotcha — option sync
`ai_chat` is written to the DB by `battleplan_updateSiteOptions()`. Some sites **call that function directly at the end of `functions-site.php`** (syncs on every load — just deploy + purge). Others only run it via the **nightly housekeeping cron (Chron B)** — there, a deploy does NOT update the DB until the cron runs; force it with `wp option update bp_force_chron_b 1` then load a page (as `battleplanweb` to bypass the lock), or patch directly (`wp option patch update ai_chat greeting "…"`). Either way **purge cache** — the greeting is baked into cached HTML.
