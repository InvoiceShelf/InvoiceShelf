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
- Entry point: `resources/scripts/main.js`
- Vue Router: `resources/scripts/admin/admin-router.js` (admin), `resources/scripts/customer/customer-router.js` (customer portal)
- State: Pinia stores in `resources/scripts/admin/stores/`
- Path aliases: `@` = `resources/`, `$fonts`, `$images` for static assets
- Vite dev server expects `invoiceshelf.test` hostname

### Backend Patterns
- **Authorization**: Silber/Bouncer with policies in `app/Policies/`. Controllers use `$this->authorize()`.
- **Validation**: Form Request classes, never inline validation
- **API responses**: Eloquent API Resources in `app/Http/Resources/`
- **PDF generation**: DomPDF (`GeneratesPdfTrait`) or Gotenberg
- **Email**: Mailable classes with `EmailLog` tracking
- **File storage**: Spatie MediaLibrary, supports local/S3/Dropbox
- **Serial numbers**: `SerialNumberFormatter` service
- **Company settings**: `CompanySetting` model (key-value per company)

### Database
Supports MySQL, PostgreSQL, and SQLite. Prefer Eloquent over raw queries. Use `Model::query()` instead of `DB::`. Use eager loading to prevent N+1 queries.

## Code Conventions

- PHP: snake_case, constructor property promotion, explicit return types, PHPDoc blocks over inline comments
- JS: camelCase
- Always check sibling files for patterns before creating new ones
- Use `config()` helper, never `env()` outside config files
- Every change must have tests (feature tests preferred over unit tests)
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP files

## CI Pipeline

GitHub Actions (`check.yaml`): runs Pint style check, then builds frontend and runs Pest tests on PHP 8.4.
