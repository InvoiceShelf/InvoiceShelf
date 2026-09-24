# Changelog

Release notes for the 3.x line. Each `##` heading is a released version, and the
section beneath it is what CI publishes to the updater — see
`.github/scripts/changelog-section.php`.

The 2.x line has its own CHANGELOG.md on the `2.x` branch. Releases are also on
GitHub: https://github.com/InvoiceShelf/InvoiceShelf/releases

## 3.0.0-alpha.7 - 2026-09-24

Seventh public alpha of InvoiceShelf 3.0, with two fixes.

⚠️ **Pre-release, not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Fixes

- **PDFs failed in a Docker container whose storage volume started empty.** The PDF renderer keeps its font cache in `storage/fonts` but never created that folder, so every invoice, estimate and payment PDF failed. The container now creates it on start. (#854)
- **Opening the address of an install while signed out showed a "page not found" screen.** It now opens the sign-in page. A signed-in user still goes to the dashboard, and an unfinished install to the setup wizard. (#856)

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.7` or `ghcr.io/invoiceshelf/invoiceshelf:3.0.0-alpha.7` (also `:next`).

## 3.0.0-alpha.6 - 2026-09-24

Sixth public alpha of InvoiceShelf 3.0. It fixes three problems that stopped a 3.x install from being created or from starting, found while preparing the public demo.

⚠️ **Pre-release, not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Fixes

- **Fresh installs on PostgreSQL stopped during the migrations**, from 3.0.0-alpha.2 on. The payment allocations migration stepped over a missing constraint in a way PostgreSQL does not allow, and every statement after it failed. (#851)
- **A Docker container with a module that ships no views did not start again.** The container prepares its caches on every start, and that step failed on the module's missing views directory. Tasks and Projects is one such module. (#851)
- **A Docker container whose storage volume started empty did not start**, because the PDF templates directory was missing. (#851)
- The sample data from `RealisticDemoSeeder` now seeds on MySQL and PostgreSQL, not only SQLite. (#851)

### Upgrade notes

- A PostgreSQL install that stopped during the migrations continues where it stopped: restart the container, or run `php artisan migrate --force`.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.6` or `ghcr.io/invoiceshelf/invoiceshelf:3.0.0-alpha.6` (also `:next`).

## 3.0.0-alpha.5 - 2026-09-24

Fifth public alpha of InvoiceShelf 3.0. AI assistants can now work in InvoiceShelf: Claude, ChatGPT, Claude Code and Cursor connect over the Model Context Protocol and read, draft and send documents with the permissions of the user who connected them. The release also brings a headless install for servers that are configured rather than clicked through, the demo mode behind demo.invoiceshelf.com, and images on GHCR.

⚠️ **Pre-release, not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Security

- **The setup wizard's token worked as a super-administrator sign-in.** Sent without the wizard's header it opened the whole API, and nothing revoked it once the install finished. It now opens the installer alone, only while the install is unfinished, and finishing the install revokes it. (#847)

### Highlights

- **Connect an AI assistant.** An MCP server at `/mcp`, off until a super administrator switches it on under Administration > Settings > AI connections (or `php artisan mcp:enable`). Assistants sign in with OAuth, and each connection is bound to one user, one company and read or read-and-write access, chosen on a consent screen. Every user sees their connected apps under Account settings, with how to connect each client, and can make one read-only or disconnect it. (#805, #806, #845)
- **39 tools.** Search and read customers, items, invoices, estimates, payments and expenses, company figures and rankings; create and update customers, items, invoices and estimates, record payments and expenses, preview a document before saving it; send documents and delete records, which need the user's confirmation. The server does the document arithmetic exactly as the invoice form does, validates with the app's own rules, and logs every change and email. (#841 to #844)
- **Headless install.** `php artisan invoiceshelf:install` migrates, creates the super administrator and the first company from options or `INSTALL_*` variables, and closes the installer. It never creates the default `admin@invoiceshelf.com` account, and does nothing on an installed app, so it can run on every start. (#847)
- **Demo mode.** With `APP_ENV=demo` the app rebuilds itself on a schedule with sample data and a customer portal sign-in, refuses the changes that would lock out the next visitor, and tells visitors they are in the demo. This is what runs demo.invoiceshelf.com. (#848)

### Improvements and fixes

- A signed-out visitor to the customer portal's login page was sent to the staff login. (#848)
- Release images are published on GHCR as well as Docker Hub. (#849)

### Upgrade notes

- **The MCP server stays off until you switch it on.** Switching it on creates the OAuth signing keys in `storage/` if there are none; the Docker image creates them on start. To keep them outside the volume, set `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`. Replacing the keys signs every connected app out.
- Hosted assistants such as Claude and ChatGPT connect only over HTTPS, to the address in `APP_URL`.
- An unfinished install's wizard token no longer works outside the installer. Finish the install in the browser, or run `php artisan invoiceshelf:install`.
- The client manifest gains a `demo` block for the apps.
- The module runtime still advertises module API 1.3.0, so modules need no change.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.5` or `ghcr.io/invoiceshelf/invoiceshelf:3.0.0-alpha.5` (also `:next`).

## 3.0.0-alpha.4 - 2026-09-23

Fourth public alpha of InvoiceShelf 3.0. It closes a batch of security vulnerabilities, several of which affect every earlier 3.x alpha, and brings the redesign: a new look that works on a phone as well as a desktop, accessibility to WCAG 2.2 AA, right-to-left languages, and the server side of the mobile apps.

⚠️ **Pre-release, not for production.** Back up your database before upgrading and use this release for evaluation and testing only. Upgrade every 3.x install you do run: the fixed vulnerabilities are public.

### Security

- **Public document links could be guessed.** The token in an emailed invoice, estimate or payment link was a Hashids encoding of the record's id, and Hashids reads only the start of its salt, so `APP_KEY` never counted and every installation gave the same record the same token. Links now carry 40 random characters, and the upgrade re-issues every existing one. (#815)
- **Document PDFs opened for anyone signed in.** `/invoices/pdf/...` and its estimate and payment twins now open only for the document's customer, or for a member of its company who may view it. (#816)
- Members could be placed in any company, with any role, through the member API, and an owner could change the password of someone who also owns another company. GHSA-c9cx (#822)
- A company owner could run commands on the server through the sendmail path in the company mail settings. The path now comes from `MAIL_SENDMAIL_PATH` alone. GHSA-9qx7, GHSA-gx2q (#823)
- Any member could make themselves owner through an invitation, cancel another company's invitations, or accept an invitation addressed to someone else. GHSA-p6v2, GHSA-xw9w, GHSA-xrm8, GHSA-528m, GHSA-6r33, GHSA-v32w (#811)
- Any member could read the company's SMTP password and API tokens, and the bootstrap sent every member the mail transport and module settings, a module's API key included. Stored secrets now come back masked. (#819)
- The generic settings endpoints read and wrote any key, so an administrator could reopen the installer or skip the validation of the mail, PDF and file disk settings. (#813)
- An HTML file with image-like bytes passed as a logo or avatar and was served from your own domain, and uploads were stored under the name the client sent. Both the name and the content must now be an accepted type, the stored name is one of our making, and `spatie/laravel-medialibrary` moves past CVE-2026-48557. GHSA-vv96, GHSA-x7rx (#812, #835)
- The roles of another company could be read by id. GHSA-72h9, GHSA-xxw7, GHSA-cv9w (#824)
- The URL of a dedicated CurrencyConverter plan could be redirected to internal addresses. GHSA-vr74 (#825)
- Any member could set the exchange rate of every foreign-currency document while a currency change left the backfill pending. Only the owner may run it now. GHSA-3838, GHSA-g7fh (#836)
- `.env.example` shipped a fixed `APP_KEY`, which the Docker image kept. GHSA-4752 (#814)

### Highlights

- **Redesigned for phones and desktops.** Geologica, refined indigo in both themes, a dark sidebar, glass surfaces, and layouts that hold up at phone width. Base components keep their names, props and slots, so modules keep working. (#802)
- **Accessible, and right to left.** The shared components meet WCAG 2.2 AA, so modules get it too, and Arabic, Persian, Hebrew and Urdu lay out right to left. (#804)
- **Ready for the mobile apps.** The server side of the thin clients: a public client manifest, CORS for the apps' origin, a list of your signed-in devices so a lost one can be cut off, and a throttled login. The apps need this release. (#797, #799, #800)
- **Custom fields that work.** The editor works again, fields attach to items, the company and users, a field says whether it prints on the document and what a valid answer is, and the API documents and validates the write path, addressing a field by its slug. (#789 to #795)
- **Every circulating currency.** One catalogue of all circulating currencies, refreshable from the admin area. (#796, #766)

### Improvements and fixes

- Headless UI, which has had no stable release since 2024, is replaced by Reka UI. Dialogs keep focus, and tabs and selects work right to left. (#807)
- A brand-new company no longer runs into dead ends and stuck screens, and its empty lists show what will be there and how to add the first record. (#809, #810)
- Form fields have one light border. (#808)
- Behind a TLS-terminating proxy the app generates https URLs, and `FORCE_HTTPS=true` settles it when the proxy's address is not known. (#788)
- The installer and the updater require PHP 8.4.1, which the bundled dependencies need. (#834)

### Upgrade notes

- **Links in emails sent before the upgrade stop working.** Customers still find their documents in the customer portal, and you can send any document again.
- **An installation still on the shipped `APP_KEY` gets a key of its own.** The Docker image generates it on first start and keeps it in `storage/app/.app_key`, the in-app updater replaces it in `.env` as its last step, and `php artisan invoiceshelf:retire-shipped-key --rotate` does it by hand. Everyone signs in again once, and the marketplace pairing is kept. A key set in the container's environment is left alone, with a warning at startup if it is the shipped one.
- **Sendmail:** a custom sendmail path moves to `MAIL_SENDMAIL_PATH` in the environment. Paths saved in the mail settings are deleted by the upgrade.
- **Members API:** `POST` and `PUT /members` accept only companies you own, each with a role that exists there.
- **Settings API:** `GET` and `POST /settings` handle only the shell settings (branding, sidebar group labels, saving PDFs to disk), and `POST /company/settings` refuses the mail transport and module settings, which have endpoints of their own.
- **Mobile apps:** `CORS_ALLOWED_ORIGINS` is only needed for an app build of your own; the default covers the official apps. `INVOICESHELF_CLIENT_MIN_VERSION` sets the oldest app version the server accepts.
- The module runtime still advertises module API 1.3.0, so modules need no change.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.4` (also `:next`).

## 3.0.0-alpha.3 — 2026-09-21

Third public alpha of InvoiceShelf 3.0. The module platform is complete and carries its first official module, and recurring invoices generate again on a container install.

⚠️ **Pre-release — not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Highlights

- **Recurring invoices generate again.** On a container install nothing ever started a scheduler, so recurring invoices were never created, invoices were never flagged overdue and estimates never expired. The image now supervises the scheduler itself, generation is driven by each schedule's due date rather than by the minute the scheduler happens to wake up, and the date shown on the schedule screen finally advances.
- **The module platform is finished.** Modules own a real full-page screen through a contracted route, register their own abilities into the role editor and have them granted and revoked across the module lifecycle, and read company members and invoice data through the SDK. Module API is now 1.3.0.
- **The first official module.** Tasks and Projects is on the marketplace: projects, tasks in list, board and week views, time tracking from anywhere a task appears, and invoicing straight from the work. This release is the first that can install it.

### Improvements and fixes

- Installing a module in a container failed outright: the image shipped no `Modules` directory, so the mounted volume came up owned by root. A module disabled on disk gained no tables when installed, and module settings came back as strings instead of the types they declare.
- The marketplace trusts a second official signing key, so releases signed with either verify.
- Owner-only navigation no longer disappears until a page reload right after signing in, sidebar groups keep their order when a module registers a low priority, select inputs keep their chevron, warning notifications are no longer styled as errors, and the header logo is sized to the header.
- The updater no longer treats a live SQLite database as a stale file to sweep away. Declined legacy invoice links survive, and `payments:restore-legacy-links` repairs installs that lost them.
- The schema consolidation prunes the migration rows it replaced instead of leaving them behind.

### Upgrade notes

- The container now runs Laravel's scheduler as a supervised service. Set `SCHEDULER_ENABLED=false` only if you drive the schedule elsewhere, such as a separate scheduler container or a second replica.
- `CRON_JOB_AUTH_TOKEN` enables the `GET /api/cron` webhook for hosts that can run neither a crontab nor a long-running process. The endpoint refuses every caller while it is unset.
- A migration moves every recurring invoice's next run into the future. Periods missed while no scheduler ran are skipped rather than billed in a rush, which is deliberate: catch-up would have emitted years of back-dated invoices on installs where the scheduler has never run.
- The module runtime uses InvoiceShelf Modules SDK 3.4.0 and advertises module API 1.3.0. Modules declaring `module_api` `^1.2.0` or `^1.3.0` install as before.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.3` (also `:next`).

## 3.0.0-alpha.2 — 2026-08-14

Second public alpha of InvoiceShelf 3.0, with major additions across payments, taxes, PDFs, and the module platform.

⚠️ **Pre-release — not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Highlights

- **Credit notes:** Create, send, and render linked full-invoice reversals. Credit notes settle the original balance, preserve the relationship in both directions, and can be safely removed to restore the balance.
- **Customer accounts and payments:** Allocate a payment across multiple invoices, retain overpayments as customer credit, inspect account activity and outstanding balances, and download or email customer statements.
- **Taxes:** Track purchase taxes on expenses and in tax reports, and apply compound sales taxes at document or item level with consistent exclusive, tax-inclusive, discount, and recurring-invoice calculations.
- **PDFs:** Dompdf and Gotenberg now share one rendering contract, with common page settings, repeating headers and footers, page numbers, broader template overrides, document metadata, and optional PDF/A output through Gotenberg.
- **Modules and AI:** Pair with the official marketplace, securely install and update signed modules, and uninstall them with data-preserving or explicitly destructive flows. The AI assistant is now a free official module instead of bundled core functionality.

### Improvements and fixes

- Require invoices to be settled before marking them completed, correct demo document sequences, and surface document save failures instead of silently leaving inconsistent state.
- Improve PDF downloads, report authorization, custom templates, fonts, line heights, margins, and access to private or Docker-internal Gotenberg hosts.
- Add MariaDB support to the installer, make the application timezone configurable, recreate required storage directories on container startup, and avoid secure-context-only browser APIs on plain HTTP installations.
- Reorganize the application into explicit domain and platform boundaries while keeping public v1 API routes and durable database data compatible.
- Harden the tag-driven release pipeline so packages are tested, built, reviewed as drafts, and registered with the updater only after publication.

### Upgrade notes

- The payment migration backfills existing invoice-payment relationships into the new allocation table and validates invalid or orphaned rows before removing the legacy relationship.
- Existing tax types are classified as sales taxes. Create purchase tax types for expense input-tax tracking.
- Core AI screens and services have moved to the official AI Assistant module. Existing AI data is retained so the module can adopt it.
- Internal PHP namespaces changed as part of the domain reorganization. Custom modules or integrations importing internal application classes must update those imports; public API routes remain compatible.
- The module runtime now uses InvoiceShelf Modules SDK 3.3.0. The unsafe `module:delete` command is replaced by `module:uninstall`, with explicit confirmation required before removing module data.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.2` (also `:next`).

## 3.0.0-alpha.1 — 2026-06-14

First public alpha of InvoiceShelf 3.0 — the next-generation rewrite (Laravel 13 / PHP 8.4, Vue 3 + TypeScript, Tailwind v4).

⚠️ **Pre-release — not for production.** For evaluation and testing only.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.1` (also `:next`).
