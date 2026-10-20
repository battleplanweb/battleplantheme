# Battle Plan Theme — functions-site.php (body classes, customer_info, optional features)

> Part of the framework reference. Index and core rules: [../CLAUDE.md](../CLAUDE.md)

## functions-site.php — The Brain of Each Site

### Body Classes
Set via `add_filter('body_class', ...)`. Pick one from each group:

| Group | Options |
|---|---|
| Header width | `header-edge` `header-full` `header-stretch` (default) |
| Menu style | `menu-clip` `split-menu` `menu-edge` `menu-full` `menu-stretch` (default) |
| Mobile menu | `side-slide` `side-push` `top-slide` `top-push` `top-drop` + optional `mobile-left` |
| Content width | `content-edge` `content-full` `content-stretch` (default) + optional `mobile-content-edge` |
| Footer width | `footer-edge` `footer-full` `footer-stretch` (default) |
| Info bar width | `info-edge` `info-full` `info-stretch` (default) |
| Sidebar | `sidebar-none` `sidebar-left` `sidebar-right` + optional `sidebar-edge` `sidebar-shift` |
| Sidebar style | `content-sidebar-box` `sidebar-line` `widget-box` (default) |
| Content box | `content-box` (default: none) |
| Accordion | `accordion-box` (default: none) |
| Forms | **Floating labels inside the field by default** (labels sit inside, float up on focus/fill). Applies to text-like fields (text/email/tel/textarea/number/date) and selects only — **radio, checkbox, checkboxes and file fields auto-revert to outside labels** (the `[seek]` shortcode detects them by `data-type` and adds `form-label-outside` for you, so you never hand-code that on a radio/checkbox field). Add `form-label-outside` yourself (on body, `.bp-form` wrapper, or a `.form-input`) to revert other fields; pair with `form-stacked` to stack those outside labels above their inputs |
| Post thumbnail | `thumb-left` `thumb-right` + optional `switch-thumb` |

### battleplan_updateSiteOptions() — All Fields

```php
$customer_info = array(
    "year"          => "202x",         // year they became a Battle Plan customer (used by [get-years])
    "founded"       => "1998",         // optional — business founding date → schema foundingDate (YYYY, YYYY-MM or YYYY-MM-DD)
    "founder"       => "Jane Doe",     // optional — schema founder; array of names for several
    "area-before"   => "",             // e.g. "(" for (469)
    "area-after"    => "-",            // separator after area code
    "area"          => "000",          // area code
    "phone"         => "000-0000",     // local number
    "name"          => "Business Name",
    "street"        => "123 Main St",
    "city"          => "City",
    "state-abbr"    => "TX",
    "state-full"    => "Texas",
    "zip"           => "75001",
    "default-loc"   => "City, TX",     // optional default location label
    "google-ad-loc" => "DFW",          // optional Google Ads location label
    "service-areas" => array(array('City', 'State'), ...),
    "service-type"  => array('HVAC solutions', 'expert installations...'),
    "service-names" => array(),
    "license"       => "TACLA00000C",
    "email"         => "email@domain.com",
    "facebook"      => "https://www.facebook.com/...",
    "instagram"     => "https://www.instagram.com/...",
    "finance-link"  => "https://...",
    "cid"           => "...",          // Google CID (no array)
    "pid"           => "...",          // Google Place ID (can be array for multi-location)
    "pid-sync"      => "false",
    "business-type" => "hvac",
    "site-type"     => "hvac",         // hvac | pedigree
    "site-brand"    => array('American Standard'),
    "google-tags"   => array(
        'prop-id'   => '000000000',    // GA4 property ID
        'analytics' => 'G-XXXXXXXX',
        'clarity'   => 'xxxxxxxxxx',   // optional: Microsoft Clarity project ID
        'ads'       => 'AW-XXXXXXXX',
        'event'     => array('phone_conversion_number', 'XXXXXXXXXXXXXXXX/XX-'),
    ),
    "scripts"       => array('magic-menu'), // optional extra scripts
    "lcp"           => 'site-background.webp',   // desktop LCP image filename
    "m-lcp"         => 'site-background-phone.webp', // mobile LCP image filename
    "cancel-holiday"=> "true",         // optional: disable holiday mode
);
```

### Optional Features (uncomment to activate)
```php
update_option('site_login', $loginArgs);      // Member login/portal system
update_option('timeline', array('install'=>'true'));
update_option('event_calendar', array('install'=>'true', 'abbr_days'=>'false'));
update_option('jobsite_geo', array('install'=>'true', ...)); // Jobsite geo tracking
```
Delete the option to disable it. Default base template has these commented out.
