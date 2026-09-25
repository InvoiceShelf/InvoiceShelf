# AGENTS.md

Canonical guide for AI coding agents working in this repository. The tool-specific files
(`CLAUDE.md`, `GEMINI.md`, `.github/copilot-instructions.md`) are gitignored symlinks to this file —
run `composer run ai-docs` to (re)create them.

## Project Overview

InvoiceShelf is an open-source invoicing and expense tracking application built with Laravel 13 (PHP 8.4) and Vue 3. It supports multi-company tenancy, customer portals, recurring invoices, and PDF generation.

## Common Commands

### Development
```bash
composer run dev          # Starts PHP server, queue listener, log tail, and Vite dev server concurrently
pnpm dev               # Vite dev server only
pnpm build             # Production frontend build
```

### Demo data
```bash
php artisan db:seed --class=DemoSeeder --force            # demo user + Acme Inc, its address and settings
php artisan db:seed --class=RealisticDemoSeeder --force   # ~100 records: customers, invoices with tax, estimates, payments, expenses, notes, a recurring invoice
```

`DemoSeeder` is what the test suite and `php artisan reset:app` run — keep it cheap. `RealisticDemoSeeder` is development-only and never runs in tests; it is what to seed when you want the app to look like a real install (a company with a logo and postal address, taxed documents, a populated notes library).

> **Local environment (preferred):** the repo ships a `./devenv` script — a Docker Compose wrapper for the full local stack. Run `./devenv` once for interactive setup (pick MySQL/PostgreSQL/SQLite, optional Gotenberg; it adds the `invoiceshelf.test` host entry), then drive it with `./devenv start | stop | shell | logs | rebuild | test | format`. App at http://invoiceshelf.test, Adminer at `:8080`, Mailpit at `:8025`; the compose files live in `docker/development/` and your choice is remembered in `.devenvconfig`. (`composer run dev` / `pnpm dev` above are the native, non-Docker alternative.)

### Testing
```bash
php artisan test --compact                        # Run all tests
php artisan test --compact --filter=testName       # Run specific test
./vendor/bin/pest --stop-on-failure                # Run via Pest directly
make test                                          # Makefile shortcut
```

Tests use SQLite in-memory DB, configured in `phpunit.xml`. Tests seed via `DatabaseSeeder` + `DemoSeeder` in `beforeEach`. Authenticate with `Sanctum::actingAs()` and set the `company` header.

### Code Style
```bash
vendor/bin/pint --dirty --format agent    # Fix style on modified PHP files
vendor/bin/pint --test                    # Check style without fixing (CI uses this)
composer lint        # = pint --test   ;  composer lint:fix = pint
pnpm lint         # eslint (--max-warnings 0)  ;  pnpm lint:fix = eslint --fix
```

### Code Quality Gate (pre-commit hook)
A committed Git hook (`.githooks/pre-commit`) runs **Pint** on staged `.php` and **ESLint** on staged
`resources/scripts/**` `.{js,cjs,mjs,ts,vue}` files, and **blocks the commit on any failure** (ESLint runs
with `--max-warnings 0`). It lints **staged files only**, and soft-skips if PHP/Pint or `node_modules` is
unavailable (CI is the backstop). The hook is enabled via `core.hooksPath`, set automatically by the
`prepare` script on `pnpm install`; to enable it manually run:
```bash
git config core.hooksPath .githooks
```
Bypass intentionally (discouraged): `git commit --no-verify`. Intentional `v-html` is allowed via an
inline `<!-- eslint-disable-next-line vue/no-v-html -->` with a reason.

### Artisan Generators
Always use `php artisan make:*` with `--no-interaction` to create new files (models, controllers, migrations, tests, etc.).

## Architecture

### Multi-Tenancy
Every major model has a `company_id` foreign key. The `CompanyMiddleware` sets the active company from the `company` request header. Bouncer authorization is scoped to the company level via `DefaultScope` (`app/Bouncer/Scopes/DefaultScope.php`).

### Roles
- **`super admin`** — global platform admin (unscoped, manages all companies).
- **`owner`** — company-level admin (scoped to a company via Bouncer, full access to that company).

### Authentication
Four guards: `web` (session), `api` (Sanctum tokens for `/api/v1/`), `customer` (session for customer portal) and `oauth` (Passport access tokens, used by the MCP server). API routes use `auth:sanctum` middleware; customer portal uses `auth:customer`.

### Routing
- **API**: All endpoints under `/api/v1/` in `routes/api.php`, grouped with `auth:sanctum`, `company`, and `bouncer` middleware
- **Web**: `routes/web.php` serves PDF endpoints, auth pages, and catch-all SPA routes (`/admin/{vue?}`, `/{company:slug}/customer/{vue?}`)

### Thin clients

Mobile clients run the same SPA from their own origin and never load `resources/views/app.blade.php`, so the public `GET /api/v1/app/client-manifest` stands in for it: version, `min_client_version`, `app_url`, page title, login branding, module script/style URLs, and the demo and managed flags. **It mirrors the Blade shell; change one and change the other** (`ClientManifestService`).

`config/cors.php` is published and covers `api/*`, the module asset routes, `reports/*` and the PDF routes. `allowed_origins` comes from `CORS_ALLOWED_ORIGINS`, defaulting to `capacitor://` and `https://` on `invoiceshelf.client.hostname`. That hostname must never be `localhost` or `127.0.0.1`: Sanctum's default stateful list holds both, so such an origin gets session and CSRF middleware and every bearer POST fails with 419.

Tokens never expire, so `GET /api/v1/auth/tokens` and `DELETE /api/v1/auth/tokens/{id}` (the caller's own only) exist to cut off a lost device, and `POST /api/v1/auth/login` is throttled to 10 a minute.

**Mobile shell** (`mobile/`, see `mobile/README.md`): a Capacitor 7 project wrapping the `pnpm build:client` output in `mobile/www`. `android/` and `ios/` are committed; `www/` and `node_modules/` are not. Native pieces live in `resources/scripts/platform/capacitor.ts` alone (device name, share-sheet file delivery, in-app browser, receipt camera, the biometric check behind the app lock in `resources/scripts/client/lock.ts`), behind a dynamic import gated on `__INVOICESHELF_CLIENT__` so no Capacitor code reaches the web bundle. The plugins are declared twice, in `mobile/package.json` and the root one, and must stay at the same versions. `capacitor.config.ts`'s `server.hostname` is the contract above: never `localhost`.

### Managed mode

`INVOICESHELF_MANAGED=true` (`config/managed.php`, deliberately outside `config('invoiceshelf')`, which the SPA bootstrap sends to every member) marks an install that a hosting provider runs for its owner, such as InvoiceShelf Cloud. The provider owns storage and backups, PDF rendering and fonts, the server's mail transport and module installation: those route files are mounted behind the `not-managed` middleware (`EnsureNotManaged`, a 403 with `error: managed_mode`), and the SPA hides their settings entries and the marketplace pairing and install controls (`utils/managed.ts`, fed by `window.managed` or the client manifest). Company mail settings, module enable/disable and everything else stay open. Mail on a managed install: the environment sets the server transport and stored global settings are ignored; a company may bring its own SMTP server only (public host, port 465, 587 or 2525, TLS or SSL, no DSN); and mail sent through the provider's transport goes out from `mail.from` with the user's chosen address as Reply-To (`OutgoingSender`, used by every mailable that takes a user-chosen sender). A new provider-owned surface goes behind `not-managed` and is listed in `ManagedModeTest`.

### MCP server

`app/Platform/Mcp/` lets AI assistants (Claude, ChatGPT, Claude Code, Cursor) use the app over the Model Context Protocol at `/mcp`, built on `laravel/mcp` and Passport. It is off until a super admin switches it on (`php artisan mcp:enable`, or Administration → Settings → AI connections). The user guide is `docs/guide/ai-assistants.md` in the docs repo.

- **Connections.** Clients register themselves (DCR) and sign in with OAuth 2.1 and PKCE. The consent screen (`OAuth/ConsentScreen`) binds each connection (`McpConnection`) to one user, one company and an access level, `read` or `write`. `BindMcpConnection` puts that company in the `company` header, so `whereCompany()`, Bouncer scoping and the policies work unchanged. A client never names a company.
- **Tools** live in `Tools/{area}/*Tool.php` and extend `McpTool`. Each one declares all four annotations and the ability it needs (`ability()`). A tool that writes extends `McpWriteTool`, which hides it from read-only connections, limits a connection to 30 changes a minute, takes an `idempotency_key` when `creates()` is true, and logs every change in `mcp_activity`. Tools that delete or send email take a required `confirm` and do nothing without it (`RequiresConfirmation`). Sends are also limited per connection and per company (`SendsMail`). `ToolCatalogueTest` enforces all of this for every tool on the server.
- **Writes use the app's own path.** A tool composes the payload the SPA would send (`SalesDocumentComposer`, `PaymentComposer`; the arithmetic is `App\Support\DocumentTaxes`, a port of the document forms), validates it with the domain's form request through `Support/DomainRequestValidator`, and stores it with the same service the controller uses. Never compute amounts in a tool, and never write a model directly.
- **Reads** return presenters (`Presenters/*`) with explicit fields, never `toArray()`. Money is `{amount, currency, formatted}` with a major-unit decimal string. Company figures sum the `base_*` columns.
- **Adding a tool:** a class in `Tools/`, listed in `Servers/InvoiceShelfServer::$tools`, and a test. Its name and description are what the model reads, so write them for someone who has never seen the app.

### Frontend
- Vue 3 + TypeScript + Pinia + vue-router + Tailwind v4 (`@tailwindcss/vite`)
- Entry point: `resources/scripts/main.ts` (single Vite input)
- Feature-folder layout under `resources/scripts/features/{admin,auth,company,customer-portal,...}` — each feature owns its own `routes.ts`, `views/`, `components/`
- Shared layers: `resources/scripts/{api,stores,components,composables,layouts,plugins,utils,types,config}`
- Path aliases: `@` → `resources/` (so most imports look like `@/scripts/api/client`, `@/scripts/stores/global.store`); `$fonts` → `resources/static/fonts`; `$images` → `resources/static/img`. There is no `@v2` alias — that was retired when the legacy v1 SPA was deleted.
- i18n: `lang/*.json` are dynamically imported by `resources/scripts/plugins/i18n.ts`. Locale-code → filename mismatches (e.g. `pt_BR` → `pt-br.json`) live in `LOCALE_FILE_MAP`. English is statically bundled; other locales lazy-load. Only edit `lang/en.json` directly — other locales are Crowdin-sourced.
- Vite dev server expects the `invoiceshelf.test` hostname (configured in `vite.config.js`)

### CSS Theme Tokens
The styling system uses **Tailwind v4 with CSS custom properties as the source of truth** — colors are not configured in JS, they live in CSS and are exposed to Tailwind via the `@theme` directive. Two files own this:

1. **`resources/css/themes.css`** — defines every color as a CSS custom property on `:root` (light) and `[data-theme="dark"]` (dark). This is where you change actual values.
2. **`resources/css/invoiceshelf.css`** — has an `@theme inline { ... }` block that **registers** each custom property as a Tailwind theme token (e.g. `--color-heading: var(--color-heading);`), making it available as utility classes (`bg-heading`, `text-heading`, `border-heading`, etc.). The block also uses the legacy `@theme { --spacing-88: 22rem; --font-base: Poppins, sans-serif; }` for non-color tokens.

**Token categories defined today:**
- `primary-{50…950}` — brand color scale
- `surface`, `surface-secondary`, `surface-tertiary`, `surface-muted` — background depth tiers
- `heading`, `body`, `muted`, `subtle` — text emphasis tiers
- `line-{light,default,strong}` — borders (`line-strong` is also the text field border)
- `control-border` — checkbox and switch outlines, 3:1 against a modal's glass
- `hover`, `hover-strong` — hover backgrounds
- `header-from`, `header-to` — fixed header gradient stops (not dark-mode-aware)
- `btn-primary`, `btn-primary-hover` — button colors (fixed, always bold)
- `status-{yellow,green,blue,red,purple}` — status badge text colors
- `alert-{warning,error,success}-{bg,text}` — alert variants

**Dark mode** is toggled via the `[data-theme="dark"]` attribute on the `<html>` element. The same custom-property names get redefined under that selector — components do **not** need `dark:` variants or conditional logic, they just reference the semantic tokens and the right value is picked up automatically.

**Adding a new color token is a two-step ritual:**
1. Add the custom property to **both** `:root` and `[data-theme="dark"]` in `themes.css`
2. Add a matching `--color-X: var(--color-X);` line inside the `@theme inline` block in `invoiceshelf.css`

After that the token is usable as `bg-X` / `text-X` / `border-X` in Vue templates and as `var(--color-X)` in raw CSS. Skip step 2 and the value exists at the CSS level but Tailwind utility classes won't be generated.

**Convention — never hardcode hex/rgb values in components.** Use the semantic tokens: `text-heading` not `text-gray-900`, `bg-surface` not `bg-white`, `border-line-default` not `border-gray-300`. Hardcoded values won't follow dark-mode flips and will diverge from the rest of the app over time. There are **no exceptions** in the project — even the auth pages (which sit outside the admin chrome) use the same `bg-surface` / `text-heading` / `border-line-default` vocabulary as `BaseCard`, just composed differently.

**Form field borders.** Text fields (inputs, textareas, selects, the multiselect, the rich editor) get their border from the form base styles in `invoiceshelf.css`, or from the `field-border` class when the field is a button or a div. Don't give a field a border colour utility such as `border-line-strong`: Tailwind orders same-property utilities alphabetically, so it would outrank `border-danger` and hide the invalid state.

**Accessibility.** The app targets WCAG 2.2 AA, and `pnpm lint` runs eslint-plugin-vuejs-accessibility. One known exception, decided on 2026-09-23: text field borders measure 1.5 to 2.3:1 against their background, below the 3:1 that SC 1.4.11 asks of a component's boundary, because 3:1 borders made forms look heavy. Fields are still identified by their labels and layout, focus turns the whole border indigo and an invalid field turns it red. Checkboxes and switches do meet 3:1. Revisit that decision before raising field borders to 3:1.

### Backend Patterns
- **Authorization**: Silber/Bouncer with policies in `app/Policies/`. Controllers use `$this->authorize()`.
- **Validation**: Form Request classes, never inline validation
- **API responses**: Eloquent API Resources in `app/Http/Resources/`
- **PDF generation**: Pluggable driver — `dompdf` (default, via `GeneratesPdfTrait`) or `gotenberg` (headless Chromium). Driver chosen per company through the **PDF Generation** admin settings page.
- **Outbound hosts**: a setting that names a host the server connects to is checked by `App\Support\Net\PrivateNetworkGuard`, at save time (`PublicHttpUrl`, `PublicHost`) and again when the connection is made. Legitimate private hosts are exempted per feature in `config/network.php` (`GOTENBERG_ALLOWED_PRIVATE_HOST`, `MAIL_ALLOWED_PRIVATE_HOSTS`) through `PrivateNetworkGuard::isExempt($feature, $target)`: the operator names the hosts, never a boolean, never a settings toggle, and one feature's exemption never covers another.
- **Email**: Mailable classes with `EmailLog` tracking. Mail driver is configurable globally and may be overridden per-company.
- **File storage**: Spatie MediaLibrary backed by the **FileDisk** model — admins create named disk entries (local / S3 / Dropbox / DigitalOcean Spaces) and assign them to purposes (`media_storage`, `pdf_storage`, `backup_storage`) in **Admin → File Disks → Disk Assignments**. New uploads go to the assigned disk; existing files stay where they were and require `php artisan media:secure` to migrate.
- **Serial numbers**: `SerialNumberService`
- **Company settings**: `CompanySetting` model (key-value per company)
- **User settings**: User-level preferences (notably `language`) stored as JSON via `setSettings()`. The sentinel value `'default'` means "inherit the company-level setting" — used for the per-user language preference so promoting/inviting members doesn't freeze a copy of the inviter's language.

### PDF Font System
PDFs ship with bundled **Noto Sans** (Latin / Greek / Cyrillic) as the default face. Non-Latin scripts come from on-demand **Font Packages** managed in **Admin → Font Packages** and defined in `FontService::FONT_PACKAGES` (`app/Services/FontService.php`). Currently shipped packages: `noto-sans` (bundled, marker only), `noto-sans-{sc,tc,jp,kr}` (CJK), `noto-sans-hebrew`, `noto-naskh-arabic` (covers `ar`/`fa`/`ur`), `noto-sans-devanagari` (`hi`), `sarabun` (Thai). `GeneratesPdfTrait::ensureFontsForLocale()` synchronously installs the matching package on the first PDF render for a given company language.

Two non-obvious constraints when extending the font system:
1. **dompdf's PHP-Font-Lib does not parse variable fonts** (`fvar`/`gvar` tables). Any new package must source **static TTF** files — Google Fonts' main repo ships variable fonts and produces empty boxes. Reliable static-TTF sources used today: `openmaptiles/fonts` for non-CJK Noto scripts, `life888888/cjk-fonts-ttf` for the CJK packages, `google/fonts/ofl/sarabun` for Thai.
2. **dompdf does not glyph-fall-back through the `font-family` chain** — it uses the *first* font for ALL characters. So locale-specific packages must be the **primary** font for that locale, not a fallback. Selection happens in `FontService::getFontFamilyForLocale()`. This is also why a Latin-locale company with a Hebrew customer name will still render boxes for the Hebrew text — solving that needs Gotenberg or a custom mid-render font-switching pass.

The bundled NotoSans is also surfaced as a `bundled: true` package entry (no download URL, files served from `resources/static/fonts/` instead of `storage/fonts/`) so it appears alongside the on-demand packages in the admin UI with a "Bundled" pill instead of an Install button.

### Database
Supports MySQL, PostgreSQL, and SQLite — **every migration and query must work on all three** (the test suite runs on SQLite `:memory:`); no vendor-specific SQL. Prefer Eloquent over raw queries. Use `Model::query()` instead of `DB::`. Use eager loading to prevent N+1 queries.

**Migrations — foreign keys are `unsignedInteger`, never `foreignId()`.** Parent tables (`users`, `companies`, `currencies`, …) key on **INT UNSIGNED**, so every reference column must match that width: declare FKs as `$table->unsignedInteger('company_id')` plus an index. **Don't use `foreignId()`** — it's BIGINT, and a `foreignId()->constrained()` against an INT PK fails on MySQL 8 with error 3780 (type mismatch), which is exactly what breaks the v2→v3 upgrade.

- **No DB-level FK constraints** for these refs — plain `unsignedInteger` columns + indexes; relationships and cascades are handled in app code, not via `->constrained()` / `cascadeOnDelete()`.
- This is the codebase-wide convention (~27 migrations use `unsignedInteger`, only 2 use `foreignId()`). The one deliberate exception is `ai_messages.conversation_id`, which references the BIGINT `ai_conversations.id` and keeps `->constrained()->cascadeOnDelete()` — that cascade is intentional and covered by `AiChatFlowTest`.

See PRs #618 / #683.

### Service Pattern
All business logic must live in Service classes (`app/Services/`), not in Models or Controllers. Controllers are thin — they authorize, call the service, and return a response. Models only contain relationships, scopes, accessors, mutators, and constants. Services are injected via constructor injection.

### Testing (TDD)
InvoiceShelf follows TDD development style:
- **Feature tests** (`tests/Feature/`) — test API routes end-to-end (HTTP requests, responses, database assertions)
- **Unit tests** (`tests/Unit/`) — test service classes and business logic in isolation
- Write tests before or alongside implementation. Every new feature or bug fix must have tests.

## Code Conventions

- PHP: snake_case, constructor property promotion, explicit return types, PHPDoc blocks over inline comments
- TS / Vue: camelCase, `<script setup lang="ts">`, prefer Composition API + Pinia stores over component-local state for anything cross-cutting
- Always check sibling files for patterns before creating new ones
- Use `config()` helper, never `env()` outside config files
- Every change must have tests
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP files
- After editing `lang/en.json` or any file under `resources/scripts/`, rebuild via `pnpm build` — the bundled chunks (including locale chunks) are content-hashed by Vite, so the browser will pick them up on hard refresh

## Releasing

Releases are cut by pushing a tag. Nothing is typed into GitHub by hand.

```bash
# 1. Add a "## <version> — <date>" section to CHANGELOG.md and bump version.md,
#    in a PR like any other change — the notes are reviewed with the code.
# 2. Once merged, tag the merge commit:
git tag 3.0.0-alpha.2 && git push origin 3.0.0-alpha.2
```

`release.yaml` then runs the tests, reads the `CHANGELOG.md` section for that tag,
builds the package with `make clean dist`, and creates a **draft** release with the
zip attached. It stops there and prints the draft URL in the run summary.

**You publish the draft yourself.** That is deliberate, not an omission: GitHub does
not start workflow runs from events created with `GITHUB_TOKEN`, so a release
published by the workflow reaches nothing downstream. Pressing Publish fires
`release: published` under your own identity, which triggers `publish.yaml` to
register the release on the updater and build the Docker images. **Until you
publish, no install is offered anything.**

Notes on the mechanics:

- **A tag with no `CHANGELOG.md` section fails the run** before anything is built,
  so a release can never go out with empty notes.
- **`prerelease` and "mark as latest" are set on the draft**, derived from the tag: a
  `-` suffix (`3.0.0-alpha.2`) means pre-release, which routes it to the **insider**
  channel so ordinary installs are not offered it. "Latest" is gated on
  `LATEST_MAJOR` in the workflows — bump it there when 3.x becomes the stable line.
- **If registration fails**, re-run it without cutting a new release: run the
  `Publish Release` workflow manually with `register_tag` set to the version.
  That path is idempotent and does not rebuild the Docker images.
- `.github/scripts/changelog-section.php <version>` prints what the updater will be
  sent, so you can check the notes locally before tagging.

**The mobile apps ride the same button.** `mobile.yaml` also listens for
`release: published`, so publishing the draft builds the Android AAB and APK
(and, separately gated, the iOS archive) from that tag, attaches the APK to the
release and uploads to the internal store tracks. Both its jobs are gated on the
repository variable `MOBILE_RELEASES_ENABLED`, so until the signing secrets exist
the whole workflow is a no-op rather than a failure on every release. The
variables, the secrets and how to produce each one are a top-to-bottom checklist
in the Releasing section of `mobile/README.md`; the version numbers come from the
tag via `mobile/scripts/version-code.mjs` and are never edited by hand.

## CI Pipeline

GitHub Actions (`check.yaml`): runs Pint style check, then runs Pest tests in parallel (`php artisan test --parallel`) on PHP 8.4 with Xdebug disabled (`coverage: none`). The test job does **not** build the frontend — the suite is API/JSON only and never renders the Vite blade, so no Node/Vite step is needed (release/docker workflows still build assets in their own jobs).
