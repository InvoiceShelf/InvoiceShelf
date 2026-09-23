<?php

use App\Domains\Accounts\AccountsServiceProvider;
use App\Domains\Catalog\CatalogServiceProvider;
use App\Domains\Contacts\ContactsServiceProvider;
use App\Domains\Metadata\MetadataServiceProvider;
use App\Domains\Money\MoneyServiceProvider;
use App\Domains\Purchases\PurchasesServiceProvider;
use App\Domains\Receivables\ReceivablesServiceProvider;
use App\Domains\Reporting\ReportingServiceProvider;
use App\Domains\Sales\SalesServiceProvider;
use App\Domains\Taxation\TaxationServiceProvider;
use App\Platform\Mail\MailServiceProvider;
use App\Platform\Mcp\McpServiceProvider;
use App\Platform\Modules\ModuleServiceProvider;
use App\Platform\Operations\OperationsServiceProvider;
use App\Platform\Pdf\PdfServiceProvider;
use App\Platform\Storage\StorageServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\ScrambleServiceProvider;
use App\Providers\ViewServiceProvider;
use App\Support\Hashids\HashidsServiceProvider;

return [
    HashidsServiceProvider::class,
    AppServiceProvider::class,
    RouteServiceProvider::class,
    StorageServiceProvider::class,
    ViewServiceProvider::class,
    PdfServiceProvider::class,
    AccountsServiceProvider::class,
    CatalogServiceProvider::class,
    ContactsServiceProvider::class,
    MetadataServiceProvider::class,
    MoneyServiceProvider::class,
    PurchasesServiceProvider::class,
    ReceivablesServiceProvider::class,
    SalesServiceProvider::class,
    TaxationServiceProvider::class,
    ReportingServiceProvider::class,
    MailServiceProvider::class,
    OperationsServiceProvider::class,
    ModuleServiceProvider::class,
    McpServiceProvider::class,
    ScrambleServiceProvider::class,
];
