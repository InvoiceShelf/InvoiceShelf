<?php

use App\Adapters\Sales\LaravelEstimateEmailSender;
use App\Adapters\Sales\LaravelInvoiceEmailSender;
use App\Adapters\Sales\MoneyDocumentExchangeRateRecorder;
use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use App\Domains\Sales\Contracts\EstimateEmailSender;
use App\Domains\Sales\Contracts\EstimatePdfDataProvider;
use App\Domains\Sales\Contracts\InvoiceEmailSender;
use App\Domains\Sales\Contracts\InvoicePdfDataProvider;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Domains\Sales\Policies\EstimatePolicy;
use App\Domains\Sales\Policies\InvoicePolicy;
use App\Domains\Sales\Policies\RecurringInvoicePolicy;
use App\Domains\Sales\SalesServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the sales domain owns document behavior authorization and commands', function () {
    expect(app()->getProviders(SalesServiceProvider::class))->toHaveCount(1)
        ->and(app(EstimatePdfDataProvider::class))->toBeInstanceOf(EstimateService::class)
        ->and(app(InvoicePdfDataProvider::class))->toBeInstanceOf(InvoiceService::class)
        ->and(app(DocumentExchangeRateRecorder::class))->toBeInstanceOf(MoneyDocumentExchangeRateRecorder::class)
        ->and(app(EstimateEmailSender::class))->toBeInstanceOf(LaravelEstimateEmailSender::class)
        ->and(app(InvoiceEmailSender::class))->toBeInstanceOf(LaravelInvoiceEmailSender::class)
        ->and(Gate::getPolicyFor(Estimate::class))->toBeInstanceOf(EstimatePolicy::class)
        ->and(Gate::getPolicyFor(Invoice::class))->toBeInstanceOf(InvoicePolicy::class)
        ->and(Gate::getPolicyFor(RecurringInvoice::class))->toBeInstanceOf(RecurringInvoicePolicy::class)
        ->and(Gate::has('send invoice'))->toBeTrue()
        ->and(Gate::has('create credit note'))->toBeTrue()
        ->and(Gate::has('send estimate'))->toBeTrue()
        ->and(Gate::has('delete multiple invoices'))->toBeTrue()
        ->and(Gate::has('delete multiple estimates'))->toBeTrue()
        ->and(Gate::has('delete multiple recurring invoices'))->toBeTrue()
        ->and(Artisan::all())->toHaveKeys(['check:estimates:status', 'check:invoices:status']);

    foreach ([
        'App\\Services\\Document\\CreditNoteService',
        'App\\Services\\Document\\DocumentItemService',
        'App\\Services\\Document\\EstimateService',
        'App\\Services\\Document\\InvoiceBalanceService',
        'App\\Services\\Document\\InvoiceService',
        'App\\Services\\Document\\RecurringInvoiceService',
        'App\\Services\\Document\\SerialNumberService',
        'App\\Policies\\CreditNotePolicy',
        'App\\Policies\\EstimatePolicy',
        'App\\Policies\\InvoicePolicy',
        'App\\Policies\\RecurringInvoicePolicy',
        'App\\Jobs\\GenerateEstimatePdfJob',
        'App\\Jobs\\GenerateInvoicePdfJob',
        'App\\Mail\\EstimateViewedMail',
        'App\\Mail\\InvoiceViewedMail',
        'App\\Mail\\SendCreditNoteMail',
        'App\\Mail\\SendEstimateMail',
        'App\\Mail\\SendInvoiceMail',
        'App\\Http\\Controllers\\Company\\Estimate\\EstimatesController',
        'App\\Http\\Controllers\\Company\\Estimate\\EstimateTemplatesController',
        'App\\Http\\Controllers\\Company\\Invoice\\InvoicesController',
        'App\\Http\\Controllers\\Company\\Invoice\\InvoiceTemplatesController',
        'App\\Http\\Controllers\\Company\\RecurringInvoice\\RecurringInvoiceController',
        'App\\Http\\Controllers\\Company\\RecurringInvoice\\RecurringInvoiceFrequencyController',
        'App\\Http\\Controllers\\Company\\General\\SerialNumberController',
        'App\\Http\\Controllers\\CustomerPortal\\Estimate\\AcceptEstimateController',
        'App\\Http\\Controllers\\CustomerPortal\\Estimate\\EstimatesController',
        'App\\Http\\Controllers\\CustomerPortal\\Invoice\\InvoicesController',
        'App\\Http\\Controllers\\CustomerPortal\\EstimatePdfController',
        'App\\Http\\Controllers\\CustomerPortal\\InvoicePdfController',
        'App\\Http\\Controllers\\Pdf\\DocumentPdfController',
    ] as $legacyClass) {
        expect(class_exists($legacyClass))->toBeFalse();
    }
});

test('the sales domain preserves company document routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match(
            '#^api/v1/(?:invoices|estimates|recurring-invoices|recurring-invoice-frequency|next-number|number-placeholders)(?:$|/)#',
            $route->uri(),
        ) === 1);

    expect($routes)->toHaveCount(37);

    foreach ($routes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Sales\\Http\\Controllers\\Company\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }
});

test('the sales domain preserves customer and pdf routes', function () {
    $customerRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/{company}/customer/invoices')
            || str_starts_with($route->uri(), 'api/v1/{company}/customer/estimates')
            || $route->uri() === 'api/v1/{company}/customer/estimate/{estimate}/status');

    expect($customerRoutes)->toHaveCount(5);

    foreach ($customerRoutes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Sales\\Http\\Controllers\\CustomerPortal\\')
            ->and($route->gatherMiddleware())->toContain('auth:customer', 'customer-portal');
    }

    $pdfRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => in_array($route->uri(), [
            'invoices/pdf/{invoice}',
            'estimates/pdf/{estimate}',
            'customer/invoices/{email_log}',
            'customer/invoices/view/{email_log}',
            'customer/estimates/{email_log}',
            'customer/estimates/view/{email_log}',
        ], true));

    expect($pdfRoutes)->toHaveCount(6);

    foreach ($pdfRoutes as $route) {
        expect($route->getActionName())->toStartWith('App\\Domains\\Sales\\Http\\Controllers\\');
    }
});
