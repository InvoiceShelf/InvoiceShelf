# Changelog

Release notes for the 2.x line. Each `##` heading is a released version, and the
section beneath it is what CI publishes to the updater — see
`.github/scripts/changelog-section.php`.

Releases before 2.4.0 are on GitHub:
https://github.com/InvoiceShelf/InvoiceShelf/releases

## 2.4.5 - 2026-09-23

Security follow-up to 2.4.4. It completes two fixes that 2.4.4 only partly covered, and corrects the PHP version the installer and the updater ask for. **Upgrade every 2.x install.**

### Security

- Logos, avatars and receipts were stored under the name the client sent, so a name such as `shell.php.jpg` passed the type check and reached web servers that run any file with `.php` anywhere in its name. The stored name now keeps only the last extension, and `spatie/laravel-medialibrary` moves to 11.23.8, past CVE-2026-48557. GHSA-x7rx (#838)
- Any member of a company could set the exchange rate of every foreign-currency document while a currency change left the backfill pending. Only the owner may run it now. GHSA-3838 (#839)

### Fixes

- The installer and the updater ask for PHP 8.4.1, which the bundled dependencies have needed since 2.2.0. Installs on PHP 8.2 or 8.3 were offered updates that left them unable to start. (#833)

### Upgrade notes

- If you are still on PHP 8.2 or 8.3, move to PHP 8.4.1 or later first. The updater now says so instead of letting the update through.
- New uploads are stored under the new names; files already uploaded keep theirs.

Docker: `invoiceshelf/invoiceshelf:2.4.5` (also `:2.4`, `:2` and `:latest`).

## 2.4.4 - 2026-09-23

Security release for the 2.x line. It closes nine vulnerabilities: two let people read documents they should never have seen, and one let an administrator run commands on the server. **Upgrade every 2.x install**, and read the upgrade notes first, because links in emails you have already sent stop working.

### Public document links could be guessed

The link in an invoice, estimate or payment email opens the document without signing in, so the token in it has to be secret. It was built from the record's number with Hashids, salted with `APP_KEY`, but Hashids reads only the start of its salt and the key never counted: every installation gave the same record the same token. Anyone could work through the numbers and open every emailed document still inside its link expiry, which is 7 days by default and unlimited if a company turned expiry off.

Links now carry 40 random characters, and the upgrade gives every existing document and sent email a new one. (#817)

### Document PDFs opened for anyone signed in

`/invoices/pdf/...`, `/estimates/pdf/...` and `/payments/pdf/...` only checked that someone was signed in, so any customer portal account, or any user of any company on the installation, could open any document whose link it had or could guess. A customer now opens only their own documents, and a user only their own company's. (#820)

### Other fixes

- Adding or editing a user trusted the list of companies it was given, so an owner could put an account into any other company on the installation with any role. A user now goes only into companies you own, with a role that exists there, and a user who also belongs to someone else's company keeps their email and password. GHSA-c9cx (#826)
- The sendmail path in the mail settings was run as a command, which let an administrator run anything on the server. It now comes from `MAIL_SENDMAIL_PATH` alone. GHSA-gx2q (#827)
- A payment could name another company's invoice, marking it paid and returning it to the caller. The invoice, customer and payment method must now belong to your company. GHSA-99x6, GHSA-95jm, GHSA-jc2f (#828)
- Roles of another company could be read by id. GHSA-72h9, GHSA-xxw7, GHSA-cv9w (#829)
- The URL of a dedicated CurrencyConverter plan was fetched as given, so it could reach internal addresses and the cloud metadata endpoint. It must now be a public address, and redirects are no longer followed. GHSA-3w68, GHSA-vr74 (#830)
- An upload whose content looked like an image passed even when its name did not, so an HTML file could be stored as a logo or avatar and served from your own domain. GHSA-vv96, GHSA-x7rx (#818)
- `.env.example` shipped a fixed `APP_KEY`, and the Docker image kept it, so Docker installs without a key of their own shared one public key. GHSA-4752 (#821)

### Upgrade notes

- **Links in emails sent before the upgrade stop working.** Customers still find their documents in the customer portal, and you can send any document again.
- **Docker:** on the first start after upgrading, the container generates an `APP_KEY` of its own and keeps it in `storage/app/.app_key`, so it survives recreating the container. Everyone signs in again once. If you set `APP_KEY` yourself nothing changes, unless you set it to the key from the old `.env.example`: the container warns about that at startup, and you should replace it.
- **Manual installs:** if your `.env` still has `APP_KEY=base64:kgk/4DW1vEVy7aEvet5FPp5un6PIGe/so8H0mvoUtW0=`, run `php artisan key:generate --force`. Everyone signs in again once; 2.x stores nothing else encrypted with it.
- **Sendmail:** if you use the sendmail driver with a custom path, set it as `MAIL_SENDMAIL_PATH` in the environment. A path saved in the mail settings is deleted by the upgrade.
- **CurrencyConverter:** the URL of a dedicated plan must be publicly reachable.

Docker: `invoiceshelf/invoiceshelf:2.4.4` (also `:2.4`, `:2` and `:latest`).

## 2.4.3 — 2026-09-21

Maintenance release for the 2.x line. Recurring invoices have never been generated on a container install, and this fixes that. Recommended for every 2.x install.

### Recurring invoices were never created

Nothing started Laravel's scheduler. No compose file defined one, no entrypoint started one, and the base image ships none, so everything on the schedule was dead: recurring invoices were never generated, invoices were never flagged overdue and estimates never expired. The image now supervises the scheduler alongside the web server, so a container install needs no crontab of its own.

The documentation made this hard to spot, because it said cron was handled for you in Docker. It never was.

### The cron webhook refused every call

`GET /api/cron` exists for hosts that can run neither a crontab nor a long-running process. Its middleware compares the request against a configuration key that was deleted in #479 without the middleware being touched, so the endpoint has rejected every caller since 2025-09-19, whatever you set in your environment.

The key is restored, the comparison is constant time, and the endpoint refuses outright when no token is configured. It also runs the scheduler at most once a minute now, so calling it more often than intended cannot generate the same invoice twice.

### The next invoice date never advanced

It was recomputed from the schedule's start date on every run, so it stayed pinned to the first occurrence and the date on the schedule screen was wrong from the first generated invoice onwards. It now counts from the present, in the company's own time zone, and a migration corrects the stored values.

### Upgrade notes

- The container now runs the scheduler as a supervised service. Set `SCHEDULER_ENABLED=false` only if you drive the schedule elsewhere, such as a separate scheduler container.
- On a bare-metal install, keep your existing crontab entry. Nothing changes for you.
- `CRON_JOB_AUTH_TOKEN` enables the webhook again. The endpoint stays refused while it is unset.
- A migration moves every recurring invoice's next run into the future. Periods missed while no scheduler ran are skipped rather than billed at once, which is deliberate on installs where the scheduler has never run.

This release also carries the release-pipeline work described under 2.4.3-beta.1 through beta.3, which has no effect on the application.

Docker: `invoiceshelf/invoiceshelf:2.4.3` (also `:2.4`, `:2` and `:latest`).

## 2.4.3-beta.3 — 2026-07-29

A third pre-release, cut to verify the release pipeline end to end after the previous one exposed a hole in it. **It contains no application changes.**

- Releases now stop at a draft, and publishing is a deliberate act. 2.4.3-beta.2 was published by the workflow itself, and because GitHub does not start workflow runs from events raised by `GITHUB_TOKEN`, nothing downstream ran — that release reached the updater only after being registered by hand, and never got Docker images.
- Laravel Boost has been removed and `AGENTS.md` is now the single source of truth for contributor and agent guidance, matching the 3.x branch.

Insider channel only. 2.4.2 remains current on stable and there is nothing here you need.

## 2.4.3-beta.2 — 2026-07-29

A second pre-release, cut to exercise the tag-triggered release flow. **It contains no application changes** — the only thing on 2.x since beta.1 is the release tooling itself:

- Releases are now cut by pushing a tag. The notes come from this file, the package is attached before the release is published, and a tag with no changelog section stops the run before anything is published.

This is the first release on this branch created without anyone publishing it by hand. Insider channel only; 2.4.2 remains current on stable and there is nothing here you need.

## 2.4.3-beta.1 — 2026-07-29

A pre-release cut to exercise the release pipeline end to end. **It contains no application changes** — everything since 2.4.2 is release tooling:

- Registration on the updater is fixed, and can now be re-run for an existing tag without production access. Previously a failure there left a release published but offered to nobody.
- Re-running a registration no longer rebuilds and republishes the `:latest` Docker image as a side effect.
- Release notes now come from `CHANGELOG.md`, so what installs are offered is written and reviewed alongside the change rather than composed at publish time.

Insider channel only. If you are on stable, 2.4.2 remains current and there is nothing here you need.

## 2.4.2 — 2026-07-29

Maintenance release for the 2.x line, fixing five issues reported against the Docker images. Recommended for all self-hosted 2.x installs.

### Containers failing to start on mounted volumes

The entrypoint now recreates `storage/framework`, `storage/logs` and `storage/app` when a volume is missing them, instead of leaving the app to die with `Please provide a valid cache path`. Docker seeds a named volume only once, when it is empty, so a volume created by an older image never gained those directories on its own.

It also no longer aborts on a `chown` it has no permission to make — a single file owned by your host user was previously enough to stop the container booting. If a mount genuinely is not writable, startup now says so and names the fix rather than failing later with a stack trace.

Fixes [docker#75](https://github.com/InvoiceShelf/docker/issues/75), [docker#69](https://github.com/InvoiceShelf/docker/issues/69); improves [docker#77](https://github.com/InvoiceShelf/docker/issues/77) and [docker#63](https://github.com/InvoiceShelf/docker/issues/63).

### The application timezone was ignored

`APP_TIMEZONE` had no effect at all, so recurring invoices and scheduled tasks always ran on UTC no matter what you configured. Setting `TIMEZONE` on the container now works as documented.

**Behaviour change:** if you have been setting `TIMEZONE` expecting it to work, your schedules will move to that timezone after upgrading. Fixes [docker#64](https://github.com/InvoiceShelf/docker/issues/64).

### Setup wizard blank on MariaDB

A fresh install using the shipped `docker-compose.mysql.yml` could not get past the database step, because that file sets `DB_CONNECTION=mariadb` and the wizard had no form for it. Fixes [docker#79](https://github.com/InvoiceShelf/docker/issues/79).

### Gotenberg on a private network

The SSRF guard added in 2.4.0 rejected `http://pdf:3000` — its own shipped default — which made the standard sidecar setup impossible to configure. Name the host you trust to permit it, and only it:

```
GOTENBERG_ALLOWED_PRIVATE_HOST=http://pdf:3000
```

Every other private address stays blocked, so the setting cannot be repointed at an internal service. Fixes [#688](https://github.com/InvoiceShelf/InvoiceShelf/issues/688).

### PHP 8.5 compatibility

`PDO::MYSQL_ATTR_SSL_CA` is deprecated in PHP 8.5; the correct constant is now resolved per version. No change on PHP 8.4.

---

Docker: `invoiceshelf/invoiceshelf:2.4.2` (also `:latest`, `:2`, `:2.4`).

## 2.4.1 — 2026-06-14

Security patch for the 2.x line.

Fixes a multi-tenant authorization issue in user management where a company owner could access user accounts belonging to another company. **All self-hosted 2.x installs should update.**

Docker: `invoiceshelf/invoiceshelf:2.4.1` (also `:latest`, `:2`, `:2.4`).

## 2.4.0 — 2026-06-12

### 2.x is entering feature freeze

InvoiceShelf **2.4.0 marks the feature freeze for the 2.x line.** New feature development now moves to the next-generation **3.x** branch. From here, **2.x will receive security patches only, through September 1, 2027 (2027-09-01)** — no new features, but it stays supported and safe to run. We recommend planning your upgrade to 3.x before then; the in-app updater path to 3.x is being prepared.

This release rolls up the final round of 2.x work: security hardening, dependency updates, groundwork for a clean v2 → v3 upgrade, a move to pnpm for the frontend toolchain, and a couple of contributor features.

### Security
- Enforce company scope on notes, estimate→invoice conversion, and user bulk-delete — GHSA-85wc, GHSA-j2vg, GHSA-wxrv (#661)
- Harden public EmailLog token endpoints — GHSA-73q7 (#662)
- Validate `ORDER BY` input on list endpoints — GHSA-cp8p (#663)
- Restrict the Gotenberg renderer host to public addresses — GHSA-mfxg (#664)
- Recompute document totals server-side so client-supplied totals aren't trusted — GHSA-8c69 (#665)
- Update dependencies to patched versions (#674): `laravel/framework` (CVE-2026-48019), `symfony/*`, `guzzlehttp/psr7`, `vite`
- Patch frontend dependencies — axios, vite, postcss, follow-redirects (#653)

### Improvements
- Add duplicate expense action (#617) — @mchev
- Support 3-decimal tax percentages, e.g. `6.625%` (#616) — @mchev
- Updater: manifest-based stale-file cleanup + cache clearing, preparing a clean v2 → v3 in-app upgrade path (#659)

### Build & Tooling
- Migrate the frontend toolchain to **pnpm** and pin a stable Vite build (#666, #674)

### Translations
- New Crowdin translation updates across many locales (#615)

> ℹ️ The CSV export work (#649) was reverted before this release and is deferred.

**Full Changelog**: https://github.com/InvoiceShelf/InvoiceShelf/compare/2.3.3...2.4.0
