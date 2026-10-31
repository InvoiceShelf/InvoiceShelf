# AGENTS.md

This is the canonical guide for AI coding agents working in InvoiceShelf. The
repository-generated files `CLAUDE.md`, `GEMINI.md`, and
`.github/copilot-instructions.md` point to this file and are ignored by Git.
Recreate those links with:

```bash
composer run ai-docs
```

## 1. Working Rules

### Before changing code

1. Read this file and any more-specific `AGENTS.md` in the directory being
   changed.
2. Inspect the existing implementation, tests, routes, and sibling components.
3. Check `git status` before editing. Treat existing changes as user-owned.
4. Identify the smallest module or application boundary that can own the fix.

### While changing code

- Preserve unrelated work. Do not reset, checkout, clean, or delete files
  unless the user explicitly requests it.
- Use the repository's existing services, requests, policies, resources, UI
  components, and tests before adding new abstractions.
- Keep controllers thin. Put business rules in services.
- Use `apply_patch` for manual edits.
- Add or update tests for every behavioral change.
- Do not commit, push, deploy, or reset data unless the user explicitly asks.
- Do not expose credentials, tokens, or local `.env` values in logs, tests, or
  documentation.

### Before finishing

Report:

- Files and behavior changed.
- Tests, formatters, and builds that passed.
- Checks that could not run and why.
- Any remaining risk or required manual verification.

## 2. Project Overview

InvoiceShelf is a Laravel 13 application using PHP 8.4, Vue 3, TypeScript,
Pinia, Tailwind CSS v4, Sanctum, Passport, and Bouncer. It provides:

- Multi-company accounting and invoicing.
- Staff administration and company-scoped authorization.
- Customer portals.
- Recurring invoices, payments, expenses, reports, and PDFs.
- Optional first-party modules under `Modules/`.

Important directories:

| Path | Responsibility |
| --- | --- |
| `app/` | Host application code |
| `app/Platform/` | Platform capabilities such as modules, MCP, operations, and PDF |
| `config/` | Configuration; use `config()` from application code |
| `database/` | Migrations, factories, seeders |
| `resources/scripts/` | Host Vue application |
| `resources/css/` | Tailwind and theme tokens |
| `Modules/` | Git submodules and module source trees |
| `routes/` | Host API and web routes |
| `tests/` | Host Pest tests |
| `docs/` | User and API documentation |
| `docker/` | Development and production Docker definitions |
| `mobile/` | Capacitor Android and iOS shell |

## 3. Local Development

### Preferred Docker environment

The repository includes `./devenv`, a Docker Compose wrapper. Run it once for
interactive setup, then use:

```bash
./devenv start
./devenv stop
./devenv shell
./devenv logs
./devenv rebuild
./devenv test
./devenv format
```

The default host is `http://invoiceshelf.test`. The setup may also provide:

- Adminer at `http://invoiceshelf.test:8080`.
- Mailpit at `http://invoiceshelf.test:8025`.

The selected compose configuration is stored in `.devenvconfig`.

### Native development

```bash
composer install
pnpm install
composer run dev
```

Use `pnpm dev` when only the Vite server is needed. Use the Docker environment
when testing MySQL, Gotenberg, container networking, or production-like
behavior.

### Docker deployment helper

`Deplay-Changes-to-Docker.ps1` is a local, ignored PowerShell helper for the
development Docker environment. It is not part of the application release.

Normal application/module update:

```powershell
.\Deplay-Changes-to-Docker.ps1 -InstallModule PaymentPlan
```

Module-only update:

```powershell
.\Deplay-Changes-to-Docker.ps1 -ModuleOnly -InstallModule PaymentPlan
```

Module-only mode installs the selected module, runs its migrations and ability
sync, clears caches, builds only the module frontend, and runs that module's
tests. It does not run host migrations, the host frontend build, host role
preset synchronization, or the host test suite.

Data-preserving and destructive operations:

- `php artisan migrate --force` applies pending migrations and preserves data.
- `-ResetDatabase` runs `migrate:fresh` and permanently deletes application
  data before seeding the base schema.
- `-ImportSeedData` also deletes application data, then imports demo data.
- Never use `migrate:fresh`, `db:wipe`, or the reset switches against a
  database that must be preserved.

## 4. Common Commands

### Backend

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list
php artisan config:show invoiceshelf
composer run ai-docs
```

### Frontend

```bash
pnpm build
pnpm lint
pnpm lint:fix
pnpm typecheck
```

After changing `resources/scripts/`, `lang/en.json`, or a module frontend,
rebuild the affected bundle. Vite assets are content-hashed, so a hard refresh
may be required in a browser.

### PHP formatting

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/pint --test
composer lint
composer lint:fix
```

## 5. Testing

### Host test suite

```bash
php artisan test --compact
php artisan test --compact --filter=testName
php artisan test --parallel --exclude-group=serial-only
./vendor/bin/pest --stop-on-failure
make test
```

The CI workflow runs:

```bash
php artisan test --parallel --exclude-group=serial-only
php artisan test --group=modules
php artisan test --group=architecture
php artisan test --group=isolated
```

Tests normally use an in-memory SQLite database configured by `phpunit.xml`.
Feature tests seed through `DatabaseSeeder` and `DemoSeeder`, authenticate with
`Sanctum::actingAs()`, and select a company with the `company` request header.

The PostgreSQL money-conversion group runs against a real PostgreSQL service.
Migrations and queries must therefore work on SQLite, MySQL/MariaDB, and
PostgreSQL.

### PaymentPlan module

Run from the InvoiceShelf root:

```bash
php artisan test --compact Modules/PaymentPlan/tests
pnpm exec vite build --config Modules/PaymentPlan/vite.config.mjs
```

On Windows, if pnpm cannot resolve the local executable, the equivalent
direct Vite command is:

```powershell
.\node_modules\.bin\vite.cmd build --config Modules/PaymentPlan/vite.config.mjs
```

Run module-specific tests after module changes. Run the full host suite when
the change crosses the host/module contract, authorization, migrations, or
shared frontend infrastructure.

## 6. Git Submodules and Modules

`Modules/PaymentPlan`, `Modules/InventoryPricing`,
`Modules/DocumentSignatureApproval`, and `Modules/.sdk/modules-master-sdk` are
Git submodules. A host repository commit records a submodule commit, not the
module's individual files.

Check both repositories when working on a module:

```bash
git status
git -C Modules/PaymentPlan status
git -C Modules/PaymentPlan branch --show-current
```

Commit and push module changes from the module repository. Then update the host
repository's submodule pointer only when the host is meant to reference the
new module commit. Do not accidentally stage module contents as host files.

A typical module installation path is:

```bash
composer dump-autoload
php artisan install:module PaymentPlan 1.0.0
php artisan modules:sync-abilities PaymentPlan
php artisan optimize:clear
```

`install:module` applies the module's pending migrations, enables the module,
registers its provider, and records its installed version. It does not reset
or reseed the database.

Each module should keep its own:

- `module.json` manifest.
- `src/` PHP code and service provider.
- `database/migrations/`.
- `resources/scripts/` and `vite.config.mjs`, where applicable.
- `tests/`.
- User documentation and implementation notes.

### PaymentPlan settings audit

The authoritative settings audit and hardening plan is:

`Modules/PaymentPlan/dev-docs/PAYMENT-PLAN-SETTINGS-AUDIT-AND-HARDENING.md`

Before adding PaymentPlan settings:

- make the schema, settings UI, API validation, service guardrails, and
  runtime behavior agree;
- enforce configured relationship and source-invoice status restrictions at
  the API and service layers;
- preserve InvoiceShelf as the source of truth for invoices, payments,
  allocations, tax, currency precision, and scheduler cadence;
- classify each setting as either a plan-time financial snapshot or a live
  company policy;
- do not add duplicate host settings or silently rewrite existing plan terms;
- document penalty cap semantics before extending overdue penalty controls.

## 7. Architecture

### Multi-tenancy

Major business models carry a `company_id`. `CompanyMiddleware` selects the
active company from the `company` request header. Company queries must use the
existing company scopes and services rather than trusting a request ID.

### Authorization and roles

InvoiceShelf uses Bouncer and policies:

- `super admin` is an installation-wide administrator and is not company
  scoped.
- `owner` is the existing company-level administrator role.
- Role presets are defined by super administrators and copied into each
  company as assignable, read-only preset roles.
- Preset copies cannot be edited or deleted by company users.
- A company user may hold multiple roles in the same company.
- Global roles can contribute access to companies when the membership is
  configured to include global roles.
- A restricted company denies the user's access even when a global role would
  otherwise grant it. A direct assignment does not override restriction.
  Access must be explicitly removed from the restricted list before a direct
  company assignment can be used.

When changing role grants:

- Write grants through `RoleGrantWriter`.
- Run writes inside the role's Bouncer company scope.
- Do not write Bouncer grants from an unscoped super-admin request.
- Avoid lazy `BouncerFacade::allow($role)->to(...)` calls that may execute
  after a temporary scope has ended; use the scoped form with the model or
  explicit `null` argument.

New abilities reach shipped presets through the `presets` entry in
`config/abilities.php`, not through a migration. `RolePresetService::applyDefaults`
adds abilities that have never been offered while preserving a deliberate
super-admin removal. Use:

```bash
php artisan roles:apply-preset-defaults
```

Do not call `syncAll()` from a migration to grant new permissions.

### Authentication

The application has four relevant guards:

- `web`: staff browser sessions.
- `api`: Sanctum tokens for `/api/v1/`.
- `customer`: customer portal sessions.
- `oauth`: Passport tokens used by the MCP server.

API routes use `auth:sanctum`. Customer portal routes use `auth:customer`.
Tokens do not expire automatically; users can list and revoke their own tokens
through the token API. Login is throttled.

### Routing

- API routes live under `/api/v1/` in `routes/api.php`.
- API groups normally include authentication, company selection, and Bouncer
  middleware.
- Web routes in `routes/web.php` serve authentication, PDFs, public documents,
  and SPA catch-all routes.
- Staff SPA routes use `/admin/{vue?}`.
- Customer portal routes use `/{company:slug}/customer/{vue?}`.

### Services and controllers

- Controllers authorize, validate through Form Requests, call services, and
  return Resources or responses.
- Business rules belong in service classes.
- Models should contain relationships, scopes, casts, accessors, mutators,
  constants, and small persistence-focused helpers.
- Use constructor injection for services.
- Use API Resources for public response shapes; do not return arbitrary model
  arrays from new endpoints.

## 8. Database Rules

- Use Eloquent and query builders instead of vendor-specific SQL.
- Prefer `Model::query()` over `DB::`.
- Eager-load relationships to avoid N+1 queries.
- Every migration must work on SQLite, MySQL/MariaDB, and PostgreSQL.
- Existing parent IDs are `INT UNSIGNED`.
- For references to those tables, use:

  ```php
  $table->unsignedInteger('company_id')->index();
  ```

- Do not use `foreignId()` for these references. It creates a BIGINT and can
  fail against the application's INT primary keys on MySQL.
- Do not add database-level foreign key constraints for ordinary application
  references; relationships and cleanup are handled by application services.
- The deliberate exception is `ai_messages.conversation_id`, which references
  the BIGINT `ai_conversations.id` with its existing cascade.
- Never make a migration depend on data that a fresh SQLite test database does
  not contain.

## 9. Frontend Rules

The host frontend uses Vue 3, TypeScript, Pinia, Vue Router, and Tailwind CSS
v4.

### Structure

- Entry point: `resources/scripts/main.ts`.
- Feature code: `resources/scripts/features/{admin,auth,company,customer-portal,...}`.
- Shared code: `resources/scripts/{api,stores,components,composables,layouts,plugins,utils,types,config}`.
- Use `<script setup lang="ts">` and the Composition API.
- Use existing base components and Pinia stores before creating new ones.
- There is no `@v2` alias. The `@` alias points to `resources/`.

### Styling

Semantic CSS tokens are defined in `resources/css/themes.css` and registered
for Tailwind in `resources/css/invoiceshelf.css`.

- Add a new color token to both light and dark theme declarations.
- Register it in the `@theme inline` block.
- Use semantic classes such as `bg-surface`, `text-heading`,
  `border-line-default`, and `text-muted`.
- Do not hard-code hex or RGB colors in Vue components.
- Do not add a border color directly to a form field when the shared field
  styles already provide it; invalid-state styles must remain visible.
- Keep forms and tables responsive. Use stable dimensions for controls and
  prevent text from overflowing its container.
- Use the existing icon library for buttons and provide labels/tooltips for
  unfamiliar icon-only actions.
- Avoid decorative marketing layouts in operational screens. Prefer dense,
  scannable, predictable interfaces.

### Localization

- English strings live in `lang/en.json`.
- Other locale files are Crowdin-managed; do not manually edit them unless
  specifically requested.
- Locale filename exceptions are defined in `LOCALE_FILE_MAP` in the i18n
  plugin.
- New user-facing module strings should follow the module's localization
  conventions and be documented in the module development notes.

## 10. Thin Clients and Mobile

Mobile clients load the built SPA from their own origin and do not load the
server Blade shell. Keep these surfaces synchronized:

- `resources/views/app.blade.php`.
- `ClientManifestService`.
- `GET /api/v1/app/client-manifest`.

The client manifest includes version, minimum client version, app URL,
branding, module assets, demo flags, and managed-mode flags.

The Capacitor shell is under `mobile/`. Native code belongs in
`resources/scripts/platform/capacitor.ts` and must be dynamically imported
behind `__INVOICESHELF_CLIENT__` so it does not enter the web bundle. Keep
plugin versions synchronized between the root and mobile package files.
`capacitor.config.ts` must not use `localhost` as the client hostname.

## 11. Managed Mode and External Hosts

`INVOICESHELF_MANAGED=true` marks an installation operated by a hosting
provider. Provider-owned routes use `not-managed` and return the managed-mode
error when unavailable. The SPA receives managed capabilities through the
bootstrap data or client manifest.

Module installation on a managed installation is allowed only when the
provider exposes a writable module directory and the module is an official,
signed release. Marketplace pairing remains provider-owned.

Outbound host settings must use `PrivateNetworkGuard` both when saved and when
connections are made. Private-host exceptions are explicit per feature in
`config/network.php`; never add a general bypass setting.

### Customer portal host

`CUSTOMER_PORTAL_URL` and `CUSTOMER_PORTAL_HOSTS` can move customer pages to a
dedicated host. Build customer links through
`App\Support\Urls\CustomerUrl`, not direct `route()` or `url()` calls. The
portal host is restricted to portal pages, portal APIs, public documents,
hashed PDFs, module assets, and `/up`.

### Branding and source links

The Powered by line is centralized in `App\Support\PoweredBy` and configured
through `config('invoiceshelf.powered_by')`. Source links use
`INVOICESHELF_SOURCE_URL` and the running version. Do not hard-code branding or
source URLs in views.

## 12. MCP Server

The MCP server lives under `app/Platform/Mcp/` and is disabled until enabled
by a super administrator.

- Connections use OAuth 2.1 and PKCE and bind to one user, company, and access
  level (`read` or `write`).
- Tools live under `Tools/{area}` and are listed in
  `Servers/InvoiceShelfServer::$tools`.
- Every tool declares its annotations and required ability.
- Write tools use the application's existing request and service paths.
- Creating tools requires an idempotency key.
- Destructive operations and mail sends require explicit confirmation.
- Read tools return explicit presenters, never `toArray()`.
- Money values use `{amount, currency, formatted}` with a major-unit decimal
  string.
- Add or update a catalogue test for every new tool.

## 13. PDF and Storage

PDF generation supports Dompdf and Gotenberg, selected per company.

Bundled and downloadable font packages are managed by `FontService`. New font
packages must:

- Use static TTF files; Dompdf cannot parse variable fonts.
- Use a release or commit URL, never a moving branch URL.
- Include a verified SHA-256 checksum.
- Respect the Dompdf chroot and `PDF_FONTS_PATH`.

Dompdf does not perform reliable glyph fallback through a font-family chain.
Locale-specific fonts therefore need to be selected as the primary font for
that locale.

Media files use the `FileDisk` model and disk assignments for media, PDFs, and
backups. Existing files remain on their original disk until explicitly
migrated with the media tooling.

## 14. Release and CI

GitHub Actions are defined under `.github/workflows/`.

The main validation workflow runs Pint, parallel Pest tests, serial module/
architecture/isolated groups, and selected Docker smoke checks. It does not
build the frontend for ordinary PHP test runs because those tests do not render
the Vite shell.

Releases are tag-driven:

1. Add the version and date to `CHANGELOG.md`.
2. Update `version.md`.
3. Merge the reviewed change.
4. Tag and push the merge commit:

   ```bash
   git tag 3.0.0-alpha.2
   git push origin 3.0.0-alpha.2
   ```

The release workflow creates a draft release. Publish the draft manually;
publishing triggers downstream registration and Docker/mobile release jobs.
Do not type release notes directly into GitHub when the repository workflow
expects `CHANGELOG.md`.

## 15. Documentation

Update documentation when behavior, settings, API contracts, installation,
permissions, accounting rules, or operational workflows change.

Use the appropriate location:

- `docs/`: host user, API, and settings documentation.
- `Modules/<Name>/docs/`: module user and API documentation.
- `Modules/<Name>/dev-docs/`: implementation plans, decisions, and trackers.
- `AGENTS.md`: repository-wide engineering rules only.

Keep implementation notes factual and mark unfinished work clearly. Do not
use the agent guide as a changelog or as a task diary.
