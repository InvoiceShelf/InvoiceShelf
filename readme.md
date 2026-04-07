<img src="https://github.com/user-attachments/assets/a6ae2080-e865-4fde-b41d-5a09964d7de2">

## Introduction

InvoiceShelf is an open-source web app that helps you track expenses, record payments, and create professional invoices and estimates. It is self-hosted, multi-tenant, and built for individuals and small businesses that want to keep their books on their own infrastructure.

The web application is built with Laravel and Vue 3.

To get started using Docker Compose, follow the [Installation guide](https://docs.invoiceshelf.com/installation.html).

# Table of Contents

1. [Documentation](#documentation)
2. [System Requirements](#system-requirements)
3. [Download](#download)
4. [Discord](#discord)
5. [Roadmap](#roadmap)
6. [Translate](#translate)
7. [License](#license)

## Documentation

- [Installation Steps](https://docs.invoiceshelf.com/installation.html)
- [User Guide](https://docs.invoiceshelf.com/)
- [Developer Guide](https://docs.invoiceshelf.com/developer-guide.html)
- [API Documentation](https://api-docs.invoiceshelf.com)

## System Requirements

- **PHP 8.4+** is required (since v2.2.0, when InvoiceShelf moved to Laravel 13).
- Database: MySQL, MariaDB, PostgreSQL, or SQLite.
- Before updating from inside the app, verify your server meets the target version's PHP and extension requirements.
- The in-app updater verifies requirements and refuses to proceed if they are not met.

## Download

- [Download Link](https://invoiceshelf.com)

## Discord

Join the discussion on the InvoiceShelf Discord: [Invite Link](https://discord.gg/eHXf4zWhsR)

## Roadmap

Rough roadmap of things to come, not in any specific order:

- [x] Automatic Update
- [x] Email Configuration
- [x] Installation Wizard
- [x] Address Customisation & Default Notes
- [x] Edit Email before Sending Invoice
- [x] Available as a Docker image
- [x] Performance Improvements
- [x] Customer View Page
- [x] Custom Fields on Invoices & Estimates
- [x] Multiple Companies
- [x] Recurring Invoices
- [x] Customer Portal
- [x] Decoupled system settings from company settings _(v3.0)_
- [x] Proper multi-tenancy system _(v3.0)_
- [x] Company member invitations with custom roles _(v3.0)_
- [x] Dark mode _(v3.0)_
- [x] Full TypeScript refactor of the frontend _(v3.0)_
- [x] Improved backend architecture _(v3.0)_
- [x] Security hardening _(v3.0)_
- [ ] **Reworked installation wizard** _(v3.0)_
- [ ] **Module Directory** _(v3.0)_
- [ ] **Rewritten Payments module** _(v3.0)_
- [ ] Accept Payments (Stripe integration)
- [ ] Improved template system for invoices and estimates

## Translate

Help us translate InvoiceShelf into your language: https://crowdin.com/project/invoiceshelf

## Star History

<a href="https://www.star-history.com/?repos=invoiceshelf%2Finvoiceshelf&type=date&legend=top-left">
 <picture>
   <source media="(prefers-color-scheme: dark)" srcset="https://api.star-history.com/chart?repos=invoiceshelf/invoiceshelf&type=date&theme=dark&legend=top-left" />
   <source media="(prefers-color-scheme: light)" srcset="https://api.star-history.com/chart?repos=invoiceshelf/invoiceshelf&type=date&legend=top-left" />
   <img alt="Star History Chart" src="https://api.star-history.com/chart?repos=invoiceshelf/invoiceshelf&type=date&legend=top-left" />
 </picture>
</a>

## License

InvoiceShelf is released under the [GNU Affero General Public License v3.0](LICENSE). See [LICENSE](LICENSE) for the full text.
