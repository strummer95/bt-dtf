# BT Transfers (repo: bt-dtf)

The DTF Studio gang sheet builder. Customers lay out transfer designs on a sheet, the
builder nests them and prices the sheet, and the order flows into WooCommerce.

**The display name and the repo slug differ.** The plugin is "BT Transfers"; the repo is
`strummer95/bt-dtf`. There is no `strummer95/bt-transfers`. The menu slug is still
`btgsb-settings` and every option, table and meta key still uses `btgsb`, deliberately, so
existing orders and settings carry over.

- Current version: **0.8.4**. Constant `BTDTF_VERSION`, function prefix `btdtf_`.
- Repo: `strummer95/bt-dtf`
- Builder UI: `bt-dtf/includes/frontend.php` (~3,660 lines), shortcode `[gang_sheet_builder]`

## STANDING RULE: uploads keep their exact size and resolution

Dillon has stated this twice (Apr 2026 and Sep 2026). Do not relitigate it.

**The file decides. Assume nothing, resize nothing.** Customers upload print-ready art.
6 inches wide at 200 DPI comes in as 6 inches wide reading 200 DPI. 4x5 at 300 comes in as
4x5 at 300.

- This covers **resolution as well as physical size.** A 200 DPI PDF is rasterized at 200,
  never upsampled to 300 and reported as 300.
- A PNG uses the DPI in its `pHYs` chunk (`readPngPhysDpi`). A PDF is placed at its real
  page size and rasterized at the resolution of the art inside it. A vector-only PDF has
  no resolution to preserve and renders at 300.
- The old `nW / 150` estimate survives **only** for a file that states no resolution at
  all. Do not widen it.
- The only permitted size change is **shrinking to fit the sheet**. Never scale up.
- Sizing lives in `defaultPrintSize()` in `includes/frontend.php`. Before 0.7.1 every
  upload was sized as if it were 150 DPI, so correct 300 DPI art came in at double size
  with a false Low DPI warning. That is the bug this rule exists to prevent.
- 0.7.3: Download PDF on the order screen builds and downloads in place (no new tab).

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

Steps: edit under `bt-dtf/`, bump both version spots, `node --check` touched JS and `php -l`
touched PHP (the container does now carry PHP 8.4), build `bt-dtf-X.Y.Z.zip` plus plain
`bt-dtf.zip` at the repo root, update `manifest.json` with the version, the **versioned**
raw `download_url` and a changelog entry, commit and push to `main`. Dillon then does
**BT Transfers → Check for updates** (the panel at the bottom of the BT Transfers page),
then **Plugins → Update Now**.

`uploads.github.com` is blocked from the container, which is why releases use versioned raw
zips rather than GitHub Release assets. The updater reads `manifest.json` through
`api.github.com` with `Accept: application/vnd.github.raw`, so a push is live instantly.

As of 0.7.5 this repo **does** carry `includes/bt-admin.php`, the shared Updates panel, and
it is byte-identical to the copies in bt-portal, bt-catalog, bt-quote and bt-accounts. Don't
fork it; if it changes, re-copy it into all **five** in the same release round.

**Where the update check lives is a fixed rule across the BT plugins:** the shared panel
is the last thing on the plugin's own top-level admin page, under whatever status that
plugin shows. Never a separate Updates submenu. BT Transfers was the one exception until
0.7.5 (a `Status & Updates` submenu) and it is not coming back; `page=bt-transfers-status`
redirects to the settings page for old bookmarks.

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
shipping method · `includes/admin.php` the Status, Modules and Updates sections ·
`includes/updater.php` · `assets/pdf-export.js` the print PDF exporter (see below)

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

## Print PDFs (0.7.0)

`assets/pdf-export.js` builds the print PDFs in the admin's browser on the hidden page
`admin.php?page=btgsb-pdf-download&oid=ORDER_ID` (`btdtf_render_pdf_download_page` in
`backend.php`). Every Download PDF link, on the order screen and in the admin email, goes there.

- **A PDF page tops out at 200" (14,400 pt).** jsPDF clamps a taller page and still draws the
  art full size, which is how order 5486 lost its bottom 72". Sheets over 200" become several
  PDFs, split between pieces and never through one.
- **Pieces come from the production ZIP at native resolution, not the combined PNG.** The
  combined PNG is capped by `buildCombinedSheet` at 16,384 px a side and 50 MP, so long sheets
  export far below 300 DPI. Treat it as a preview.
- Checkout posts `layout` (`{w, h, p:[{i,x,y,w,h,r}]}`, `i` = index into designs[] = manifest
  row = ZIP file), stored as order item meta `_btgsb_layout`. Orders without it are repacked
  onto rows from the manifest. Orders without a ZIP fall back to slicing the combined PNG at
  transparent rows.
- Pure logic (`paginate`, `packManifest`, `planFromJob`) is exported for Node, so it can be
  tested without a browser.

## Order type on the Orders list (0.7.4)

A gang sheet order and an exchange-form order were indistinguishable in the Woo orders list:
order number, customer, date, status, total, origin, all identical. `btdtf_order_kind()` in
`backend.php` labels each row, with a Type column and a filter link per kind beside the
existing Awaiting ones.

- DTF is read from the gang sheet meta on the line items. **Exchange is read from
  `_bt_exchange_items` / `_bt_original_order`, which the `[bt_exchange_form]` WPCode snippet
  writes.** That snippet is in no repo; those keys are read here, never written.
- The line-item scan is cached in `_btgsb_order_kind` (the Orders list would otherwise
  re-read every order's items on every load) using `save_meta_data()`, never `save()` —
  rendering a list must not move a status or send mail.
- **The exchange keys are checked live and beat the cache.** The snippet writes them after
  the order exists, so a cached "none" would otherwise stick forever. The exchange filter
  and count read those keys too, for the same reason.
- `btdtf_kind_backfill()` stamps pre-0.7.4 orders 200 at a time on admin loads, gated by
  `btgsb_kind_backfill_v1`, so the DTF filter count is not limited to orders somebody
  happened to scroll past.
