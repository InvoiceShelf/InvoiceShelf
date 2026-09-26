# Changelog

Release notes for the 3.x line. Each `##` heading is a released version, and the
section beneath it is what CI publishes to the updater — see
`.github/scripts/changelog-section.php`.

The 2.x line has its own CHANGELOG.md on the `2.x` branch. Releases are also on
GitHub: https://github.com/InvoiceShelf/InvoiceShelf/releases

## 3.0.0-alpha.10 - 2026-09-26

Tenth public alpha of InvoiceShelf 3.0. Owners of a managed install can now add official modules themselves when their host allows it, and every Docker start checks installed modules against the running version.

⚠️ **Pre-release, not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Highlights

- **Official modules on managed installs.** When the hosting provider mounts a writable `Modules/` directory, the owner installs, updates and removes official modules from Administration → Modules, as on any other install. Only signed releases from the official marketplace install. Pairing with a marketplace account stays with the provider, and paid modules are not offered there yet. Without a writable `Modules/` directory, a managed install behaves as before. (#893)
- **`php artisan modules:reconcile`** disables every enabled module whose `module.json` no longer fits the running InvoiceShelf version, module API, PHP version or extensions, and records the reason on the module. The Docker image runs it on every start, before migrations, so an upgrade never boots or migrates a module built for another version. (#893)

### Improvements and fixes

- **Installing, updating or removing a module now clears PHP's opcode cache.** On servers set never to re-read PHP files (`opcache.validate_timestamps=0`), an updated module otherwise kept running its old code until PHP restarted. (#893)

### Upgrade notes

- **Docker:** the image runs `modules:reconcile` when it starts. A module disabled that way keeps its data. Enable it again after installing a release that fits.
- **Hosting providers:** managed installs now reach install and uninstall whenever `Modules/` is writable. Leave it read-only to keep modules in your hands.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.10` or `ghcr.io/invoiceshelf/invoiceshelf:3.0.0-alpha.10` (also `:next`).

## 3.0.0-alpha.9 - 2026-09-26

Ninth public alpha of InvoiceShelf 3.0. The super administrator can now define role presets that every company gets, and create users and place them in companies from Administration.

⚠️ **Pre-release, not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Highlights

- **Role presets.** The super administrator defines roles once in Administration → Settings → Role Presets, and every company gets each one as a role it can assign but not change. Three ship: Owner (every permission), Manager (everything except managing custom fields and exchange-rate providers) and Read only (every view permission and the dashboard). Editing a preset updates it in every company; a preset still held by a member or offered in a pending invitation cannot be deleted. Companies keep their own roles. (#860, #887, #888)
- **Create users in Administration.** Users → New User creates an account with a password, a super-administrator switch and the companies it belongs to, with a role in each. An existing user's companies and roles are edited the same way. (#859, #889)
- **One permission grid** for role presets and company roles, with the shortcuts from alpha.8, and a View button that shows a preset's permissions from a company. (#888)
- `php artisan invoiceshelf:send-welcome --email=...` emails a user a link to set their password, for hosts that install with a random password and hand the instance over later. It fails when the mail is not sent. (#891)

### Improvements and fixes

- **A role with only the view permission for items, payments or expenses could add, rename and delete units, payment modes and expense categories.** Changing them now needs the edit permission, and adding them the create or edit permission. The settings pages hide those actions from members who cannot use them. (#886)
- A company owner could rename the Owner role or remove its permissions through the API. Owner and the preset roles cannot be edited or deleted from a company, and a company role can no longer be named `owner` or start with `preset:`. (#887)
- A member removed from a company kept the role they held there, so inviting them again gave it back. Leaving a company now removes it. (#889)
- A role's title follows its name when it is renamed. (#887)

### Upgrade notes

- **API:** company resources no longer include `roles`. Read a company's roles from the roles endpoint. (#887)
- The upgrade creates the three presets and gives every existing company its copies; the company's own roles are left alone. `php artisan roles:sync-presets` (optionally `--company=`) repairs a company's copies.
- **Custom roles that relied on the view-only behaviour above** lose the ability to change units, payment modes and expense categories. Give them the edit permission to keep it.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.9` or `ghcr.io/invoiceshelf/invoiceshelf:3.0.0-alpha.9` (also `:next`).

## 3.0.0-alpha.8 - 2026-09-25

Eighth public alpha of InvoiceShelf 3.0. It closes security issues in the mail, file disk and installer settings, fixes foreign-currency documents on PostgreSQL, and makes the Docker image ready for hosting: it runs on a read-only filesystem, gives the customer portal a host of its own and carries its PDF fonts.

⚠️ **Pre-release, not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Security

- **A company owner could make the server connect to internal addresses** through the company mail settings, with an SMTP host or mail URL on a private or reserved network, and use the test mail to probe them. A company mail server must now be a public host. The super administrator keeps the private network, and `MAIL_ALLOWED_PRIVATE_HOSTS` names internal relays any owner may use. GHSA-r234 (#871)
- Any request could pick the file disk the app wrote to for its whole lifetime with a `file_disk_id` parameter. Only the backup screens choose a disk now, for themselves. (#869)
- A file disk's saved credentials could set the storage driver and other internal options. The driver comes only from the disk's type, which must be one InvoiceShelf supports, and S3-compatible disks are checked like S3 ones. (#870)
- The installer's wizard token never expired. It now lasts two hours (`INSTALL_WIZARD_TOKEN_TTL`, in minutes). (#872)

### Highlights

- **Runs on a read-only filesystem.** With `INVOICESHELF_DOTENV=false` the Docker image starts without a `.env` and takes every setting, `APP_KEY` included, from the environment. Only `storage/` and a few temporary mounts need to be writable, and CI now boots the image that way on every change to it. (#879)
- **The customer portal on a host of its own.** `CUSTOMER_PORTAL_URL` (and `CUSTOMER_PORTAL_HOSTS` for more than one) moves every link sent to a customer there. That host serves the portal and public documents only, and customer pages opened on the app host move to it. (#881)
- **Managed mode for hosting providers.** `INVOICESHELF_MANAGED=true` hides the storage, backup, PDF, server mail and module install settings from the install's owner, who then brings only their own SMTP server, and sends platform mail from the platform address with the user as Reply-To. (#875, #878)
- **PDF fonts built into an image.** `php artisan pdf:fonts:install --all --path=...` downloads the language font packages ahead of time, `PDF_FONTS_PATH` points at them and `PDF_FONTS_DOWNLOAD=false` stops downloads while the app runs. Every font now comes from a fixed release or commit and is checked against its SHA-256. (#882)
- **Headless installs** can give the administrator a random password and email them a link to set their own (`--admin-password-random --send-welcome`), read the password from a file (`INSTALL_ADMIN_PASSWORD_FILE`), and set the date format and fiscal year. (#876)

### Improvements and fixes

- **Owners can add a member with a password**, next to Invite member on the Members page, for a server that sends no mail or a colleague who should not wait for an invitation. (#859, #883)
- **Building a company role takes a few clicks:** View Only next to Select All and None, and All, View and None on each module, with the permissions they depend on ticked for you. Select All no longer adds every permission twice. (#860, #884)
- **On PostgreSQL, saving an estimate, invoice or expense in a foreign currency failed** whenever the exchange rate had decimals, with `invalid input syntax for type bigint`. Amounts converted to the company currency are now rounded to whole cents before they are stored, everywhere they are written: documents, lines, taxes, copies, recurring invoices, payments and balances. The exchange rate keeps its decimals. (#798, #862, #864)
- The exchange-rate update for existing documents, which runs after the company currency changes, took each document's discount from its subtotal and converted taxes twice. It now converts each amount from its own value. (#862)
- **Installing or removing a module from the marketplace could fail at the last step**, while clearing caches, after the module had been downloaded and verified. (#858)
- **The customer picker on invoice, estimate and recurring invoice forms logged an error in the browser console**, and keyboard focus did not follow a pick or a clear: it now moves to the customer card after a pick and back to the picker after Deselect. (#865)
- Overdue invoices and expired estimates are flagged even when the server was down at midnight: `invoiceshelf:catch-up` runs the day's checks once if they have not run, hourly and when the Docker image starts. (#877, #879)
- SQLite waits up to five seconds for a lock instead of failing with "database is locked" (`DB_BUSY_TIMEOUT`), and `DB_JOURNAL_MODE` and `DB_SYNCHRONOUS` can turn on WAL. (#874)
- Saving company SMTP settings with the optional fields left blank no longer fails. (#871)
- The company mail settings no longer fail to load their list of mail drivers for a company owner who is not the super administrator. (#878)
- The Settings link in the administration area no longer leads to a missing page. (#875)
- A PDF font package that cannot be downloaded no longer stops the document: it is logged and the PDF uses Noto Sans. (#882)
- Sign-in pages, public documents and document emails take their "Powered by" line from one setting (`INVOICESHELF_POWERED_BY`, `_NAME`, `_URL`), and the account menus link to the source code of the running version (`INVOICESHELF_SOURCE_URL`). (#880)

### Upgrade notes

- Documents saved before this release keep the company-currency amounts they were stored with. Saving a document again recalculates them.
- **Company mail on an internal address:** a company mail server on a private address that an owner who is not the super administrator saved before this release keeps sending, but its test mail is refused and saving its settings asks for a public host. List the relay in `MAIL_ALLOWED_PRIVATE_HOSTS` to keep it.
- **Docker:** the image now sets `CONTAINERIZED=true` in its environment, runs `invoiceshelf:catch-up` when it starts, and refuses to start on the key InvoiceShelf used to ship when `INVOICESHELF_MANAGED` is on or there is no `.env`.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.8` or `ghcr.io/invoiceshelf/invoiceshelf:3.0.0-alpha.8` (also `:next`).

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
