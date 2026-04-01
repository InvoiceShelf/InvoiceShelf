# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

InvoiceShelf is an open-source invoicing and expense tracking web app built with **Laravel 13** (PHP 8.4+) and **Vue 3**. It supports multiple companies, recurring invoices, a customer portal, custom fields, and a modular plugin system.

## Commands

### Development

```bash
composer run dev          # Start all dev services concurrently: artisan serve, queue, pail logs, npm dev
npm run dev               # Vite dev server only
npm run build             # Production frontend build
```

### Testing

```bash
php artisan test --compact                          # Run all tests
php artisan test --compact --filter=TestClassName   # Run specific test
php artisan make:test --pest {Name}                 # Create feature test
php artisan make:test --pest --unit {Name}          # Create unit test
```

### Code Formatting

```bash
vendor/bin/pint --dirty --format agent   # Format changed PHP files (run after any PHP edits)
```

### Artisan

```bash
php artisan make:<type> --no-interaction   # Always pass --no-interaction
php artisan route:list                     # List all routes
php artisan list                           # List all available commands
```

## Architecture

### Backend (Laravel 13)

- **API-first**: All frontend data flows through versioned REST API at `/api/v1/`
- **Authentication**: Laravel Sanctum (Bearer tokens + SPA cookie auth)
- **Controllers**: `app/Http/Controllers/V1/` — split into `Admin/` and `Customer/` namespaces. Single-action controllers preferred (e.g., `SendInvoiceController`, `ChangeInvoiceStatusController`)
- **Services**: `app/Services/` — business logic (PDFService, SerialNumberFormatter, module management)
- **Authorization**: Bouncer package for RBAC (`silber/bouncer`), plus Laravel Policies in `app/Policies/`
- **Validation**: Always use Form Request classes (`app/Http/Requests/`), never inline validation
- **Media**: Spatie MediaLibrary for file handling; activate `medialibrary-development` skill when working with it
- **PDF generation**: DomPDF or Gotenberg via `PDFService`
- **Config**: Use `config('key')` everywhere; `env()` only inside config files

### Frontend (Vue 3)

- **Entry point**: `resources/scripts/main.js`
- **State**: Pinia stores in `resources/scripts/stores/`
- **Routing**: Vue Router in `resources/scripts/router/`
- **Two portals**: `resources/scripts/admin/` and `resources/scripts/customer/`
- **API calls**: Axios-based services in `resources/scripts/services/`
- **Styling**: Tailwind CSS v3; activate `tailwindcss-development` skill when writing Tailwind classes

### Data Model

Core entities: `Company`, `User`, `Customer`, `Invoice`, `Estimate`, `Payment`, `Expense`. All are company-scoped via `company_id`. `CustomField` / `CustomFieldValue` enable dynamic fields without migrations.

### Testing

- Pest v4 with PHPUnit v12
- Feature tests (most tests) in `tests/Feature/`, Unit tests in `tests/Unit/`
- Test DB: SQLite in-memory (configured in `phpunit.xml`)
- Use model factories; check for existing factory states before manually setting attributes
- Every change must be tested — write or update tests before finalizing

## Key Conventions

- Follow sibling file patterns for structure, naming, and approach before writing new code
- PHP: constructor property promotion, explicit return types, PHPDoc blocks with array shapes
- Enums: TitleCase keys
- Eloquent: eager-load relationships to prevent N+1; avoid raw `DB::` queries
- Named routes and `route()` helper for URL generation
- Queued jobs (`ShouldQueue`) for time-consuming operations
- Activate Laravel Boost skills when working in their domains: `pest-testing`, `tailwindcss-development`, `medialibrary-development`

## Local Dev Environment

The app runs via **Laravel Herd** at `https://invoiceshelf.test` (or http). Do not run extra serve commands — Herd handles it automatically.

## Docker (Production)

`docker/production/` contains a multi-stage Dockerfile (Node build → PHP-FPM + Nginx) and `docker-compose.mysql.yml`. The app runs on port 8099 (mapped to 8090 internally). SQLite variant: `docker-compose.sqlite.yml`.
