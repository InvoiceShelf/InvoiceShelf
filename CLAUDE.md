# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

InvoiceShelf is an open-source invoicing and expense tracking application built with Laravel 13 (PHP 8.4) and Vue 3. It supports multi-company tenancy, customer portals, recurring invoices, and PDF generation.

## Common Commands

### Development
```bash
composer run dev          # Starts PHP server, queue listener, log tail, and Vite dev server concurrently
npm run dev               # Vite dev server only
npm run build             # Production frontend build
```

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
```

### Artisan Generators
Always use `php artisan make:*` with `--no-interaction` to create new files (models, controllers, migrations, tests, etc.).

## Architecture

### Multi-Tenancy
Every major model has a `company_id` foreign key. The `CompanyMiddleware` sets the active company from the `company` request header. Bouncer authorization is scoped to the company level via `DefaultScope` (`app/Bouncer/Scopes/DefaultScope.php`).

### Authentication
Three guards: `web` (session), `api` (Sanctum tokens for `/api/v1/`), `customer` (session for customer portal). API routes use `auth:sanctum` middleware; customer portal uses `auth:customer`.

### Routing
- **API**: All endpoints under `/api/v1/` in `routes/api.php`, grouped with `auth:sanctum`, `company`, and `bouncer` middleware
- **Web**: `routes/web.php` serves PDF endpoints, auth pages, and catch-all SPA routes (`/admin/{vue?}`, `/{company:slug}/customer/{vue?}`)

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
- `line-{light,default,strong}` — borders
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

### Backend Patterns
- **Authorization**: Silber/Bouncer with policies in `app/Policies/`. Controllers use `$this->authorize()`.
- **Validation**: Form Request classes, never inline validation
- **API responses**: Eloquent API Resources in `app/Http/Resources/`
- **PDF generation**: Pluggable driver — `dompdf` (default, via `GeneratesPdfTrait`) or `gotenberg` (headless Chromium). Driver chosen per company through the **PDF Generation** admin settings page.
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
Supports MySQL, PostgreSQL, and SQLite. Prefer Eloquent over raw queries. Use `Model::query()` instead of `DB::`. Use eager loading to prevent N+1 queries.

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
- After editing `lang/en.json` or any file under `resources/scripts/`, rebuild via `npm run build` — the bundled chunks (including locale chunks) are content-hashed by Vite, so the browser will pick them up on hard refresh

## CI Pipeline

GitHub Actions (`check.yaml`): runs Pint style check, then builds frontend and runs Pest tests on PHP 8.4.
