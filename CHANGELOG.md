# Changelog

Release notes for the 3.x line. Each `##` heading is a released version, and the
section beneath it is what CI publishes to the updater — see
`.github/scripts/changelog-section.php`.

The 2.x line has its own CHANGELOG.md on the `2.x` branch. Releases are also on
GitHub: https://github.com/InvoiceShelf/InvoiceShelf/releases

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
