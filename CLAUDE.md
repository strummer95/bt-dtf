# BT Transfers (repo: bt-dtf)

The DTF Studio gang sheet builder. Customers lay out transfer designs on a sheet, the
builder nests them and prices the sheet, and the order flows into WooCommerce.

**The display name and the repo slug differ.** The plugin is "BT Transfers"; the repo is
`strummer95/bt-dtf`. There is no `strummer95/bt-transfers`. The menu slug is still
`btgsb-settings` and every option, table and meta key still uses `btgsb`, deliberately, so
existing orders and settings carry over.

- Current version: **0.4.1**. Constant `BTDTF_VERSION`, function prefix `btdtf_`.
- Repo: `strummer95/bt-dtf`
- Builder UI: `bt-dtf/includes/frontend.php` (~3,660 lines), shortcode `[gang_sheet_builder]`

## Environment

**Boomer T's Ink & Thread is a separate company from Duck and Rabbit Co.** Dillon's dad's
shop. **AWS Lightsail Bitnami WordPress + Elementor, NOT IONOS.** Never conflate with
PresStora.

**Dillon works only through the WordPress dashboard.** No SSH, no SFTP. Everything ships as
a plugin update.

## Release process

Four places must match or WordPress loops forever trying to reinstall:

1. `Version:` in `bt-dtf/bt-dtf.php`
2. `BTDTF_VERSION` in the same file
3. `version` in `manifest.json`
4. The version inside the zip

Steps: edit under `bt-dtf/`, bump both version spots, `node --check` touched JS (no PHP
binary in the container, brace-audit by hand), build `bt-dtf-X.Y.Z.zip` plus plain
`bt-dtf.zip` at the repo root, update `manifest.json` with the version, the **versioned**
raw `download_url` and a changelog entry, commit and push to `main`. Dillon then does
**BT Transfers → Status & Updates → Check for updates now**, then **Plugins → Update Now**.

`uploads.github.com` is blocked from the container, which is why releases use versioned raw
zips rather than GitHub Release assets. The updater reads `manifest.json` through
`api.github.com` with `Accept: application/vnd.github.raw`, so a push is live instantly.

Note this repo does **not** carry `includes/bt-admin.php`, the shared Updates panel used by
bt-portal, bt-catalog, bt-quote and bt-accounts. It has its own Status & Updates page.

## This plugin is a port, and the port rules still apply

It started as four WPCode snippets under "DTF Studio": Backend, Shipping, Save & Resume,
and Frontend. All four now live in the plugin, but the coexistence machinery is still there
and must stay.

- **Every function is `btdtf_`, every constant `BTDTF_`.** Version 0.1.0 kept the snippets'
  `btgsb_*` function names and activating it alongside the live snippet caused a PHP
  redeclare that took the whole site down. Zero names in common is what makes a redeclare
  impossible in any activation order. Never reintroduce a `btgsb_` function name.
- **Nothing registers at load.** Hooks wait for `plugins_loaded` priority **999**, after
  WPCode has evaluated its snippets. Each module stands down independently if its own
  snippet is still active: no menu, no AJAX, no order columns, no duplicate meta, plus an
  admin notice explaining why. That per-module dormancy is what let Dillon cut over one
  snippet at a time instead of all at once.
- **Conflict detection reads the database, not `function_exists`.** Plugins load before
  WPCode evaluates snippets, so `function_exists` cannot work here. Don't "simplify" it.
- Option names, order meta keys, AJAX actions, the nonce, page slugs, DOM ids, CSS classes
  and the `wp_btgsb_saves` table are all unchanged from the snippets. The shipping method
  id is still `btgsb_shipping` so configured zone rates carry over. Renaming any of these
  breaks live orders and existing resume links.

## Structure

`bt-dtf.php` main and the dormancy boot · `includes/frontend.php` the builder UI, the
`[gang_sheet_builder]` shortcode, embedded fonts, nesting and pricing logic ·
`includes/backend.php` orders, admin columns, pricing tiers · `includes/save.php` Save &
Resume, the `wp_btgsb_saves` table, AJAX, nightly cron · `includes/shipping.php` the
shipping method · `includes/admin.php` Sheet Settings and Status & Updates ·
`includes/updater.php`

There are 76 builder functions in `frontend.php`. Before a release touching it, verify they
are each present exactly once with no unresolved internal calls. That check was run for
0.4.0 and is worth repeating.

## Input handling in the builder

0.4.1 fixed the W and H boxes on a design card accepting only one character. Both committed
on **every keystroke**, which clamped the partial number and rebuilt the card list,
destroying the input being typed into. Typing 12 committed the 1 and threw the field away
before the 2 landed.

They now commit on blur or Enter, the same way the copies box already did. Clicking into
either box selects its contents so you can type straight over it.

**Any numeric field in the builder must commit on blur or Enter, never on input**, because
a commit rebuilds the card list. This is the pattern to follow for new fields.

## Removed on purpose

The four custom order statuses (Art Approved, Printing, Ready for Pickup, Shipped) and
their two customer emails were removed and should stay removed. The Awaiting Items tracking
flag replaced them: a badge and filter on the Orders list that **never changes an order's
WooCommerce status**, so flagged orders cannot drop out of the list.

## Working notes

- Compact, always. Text sizing errs UP: table body 15px or larger, badges 13px or larger.
- Terse and results-first. Ship the deliverable, not narration.
- The plugin sits at admin menu position 58 so it groups with BT Catalog, BT Portal and
  BT Quote rather than floating above them.
- Changelog entries in `manifest.json` are read by shop staff, not developers. Match the
  existing plain-language voice: what changed, what it means, and what actually caused it.
