<?php

namespace App\Domains\Sales;

use App\Adapters\Sales\LaravelEstimateEmailSender;
use App\Adapters\Sales\LaravelInvoiceEmailSender;
use App\Adapters\Sales\MoneyDocumentExchangeRateRecorder;
use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Console\CheckEstimateStatus;
use App\Domains\Sales\Console\CheckInvoiceStatus;
use App\Domains\Sales\Console\GenerateRecurringInvoices;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use App\Domains\Sales\Contracts\EstimateEmailSender;
use App\Domains\Sales\Contracts\EstimatePdfDataProvider;
use App\Domains\Sales\Contracts\InvoiceEmailSender;
use App\Domains\Sales\Contracts\InvoicePdfDataProvider;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Domains\Sales\Policies\CreditNotePolicy;
use App\Domains\Sales\Policies\EstimatePolicy;
use App\Domains\Sales\Policies\InvoicePolicy;
use App\Domains\Sales\Policies\RecurringInvoicePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    /**
     * Abilities answered by a policy method rather than by a model instance,
     * as ability name => [policy class, policy method].
     */
    private const POLICY_ABILITIES = [
        'send invoice' => [InvoicePolicy::class, 'send'],
        'create credit note' => [CreditNotePolicy::class, 'create'],
        'send estimate' => [EstimatePolicy::class, 'send'],
        'delete multiple invoices' => [InvoicePolicy::class, 'deleteMultiple'],
        'delete multiple estimates' => [EstimatePolicy::class, 'deleteMultiple'],
        'delete multiple recurring invoices' => [RecurringInvoicePolicy::class, 'deleteMultiple'],
    ];

    public function register(): void
    {
        $this->app->bind(EstimatePdfDataProvider::class, EstimateService::class);
        $this->app->bind(InvoicePdfDataProvider::class, InvoiceService::class);
        $this->app->bind(DocumentExchangeRateRecorder::class, MoneyDocumentExchangeRateRecorder::class);
        $this->app->bind(EstimateEmailSender::class, LaravelEstimateEmailSender::class);
        $this->app->bind(InvoiceEmailSender::class, LaravelInvoiceEmailSender::class);
    }

    public function boot(): void
    {
        $this->commands([
            CheckEstimateStatus::class,
            CheckInvoiceStatus::class,
            GenerateRecurringInvoices::class,
        ]);

        Gate::policy(Estimate::class, EstimatePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(RecurringInvoice::class, RecurringInvoicePolicy::class);

        foreach (self::POLICY_ABILITIES as $ability => $handler) {
            Gate::define($ability, $handler);
        }
    }
}
