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
pnpm dev                  # Vite dev server only
pnpm build                # Production frontend build
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
- **Authorization**: Silber/Bouncer with policies in `app/Policies/`. Controllers use `$this->authorize()`. A policy that is handed a record asks `$user->isActingIn($record->company_id)` (member of that company and Bouncer scoped to it), never `hasCompany()` alone: Bouncer answers with the abilities of the company in the `company` header, so membership by itself would let a role held in one company act on another company's records. Code that authorizes outside the header (PDF links) scopes Bouncer to the record's company first, as `DocumentPdfAccess` does. New record types go into the dataset in `tests/Feature/Admin/ActiveCompanyAuthorizationTest.php`.
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

## Releasing

Releases are cut by pushing a tag. Nothing is typed into GitHub by hand.

```bash
# 1. Add a "## <version> — <date>" section to CHANGELOG.md and bump version.md,
#    in a PR like any other change — the notes are reviewed with the code.
# 2. Once merged, tag the merge commit:
git tag 2.4.3 && git push origin 2.4.3
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
  `-` suffix (`2.4.3-beta.1`) means pre-release, which routes it to the **insider**
  channel so ordinary installs are not offered it. "Latest" is gated on
  `LATEST_MAJOR` in the workflows — bump it there when 3.x becomes the stable line.
- **If registration fails**, re-run it without cutting a new release: run the
  `Publish Release` workflow manually with `register_tag` set to the version.
  That path is idempotent and does not rebuild the Docker images.
- `.github/scripts/changelog-section.php <version>` prints what the updater will be
  sent, so you can check the notes locally before tagging.

## CI Pipeline

GitHub Actions (`check.yaml`): runs Pint style check, then builds frontend and runs Pest tests on PHP 8.4.
