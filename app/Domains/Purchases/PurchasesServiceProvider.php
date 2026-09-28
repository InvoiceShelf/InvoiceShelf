<?php

namespace App\Domains\Purchases;

use App\Adapters\Purchases\MediaLibraryExpenseReceiptManager;
use App\Adapters\Purchases\MoneyExpenseExchangeRateRecorder;
use App\Adapters\Purchases\TaxationExpenseTaxManager;
use App\Domains\Purchases\Application\ClearExpenseTaxes;
use App\Domains\Purchases\Application\ProtectCreditedExpenses;
use App\Domains\Purchases\Application\ProtectPurchaseTaxes;
use App\Domains\Purchases\Contracts\ExpenseExchangeRateRecorder;
use App\Domains\Purchases\Contracts\ExpenseReceiptManager;
use App\Domains\Purchases\Contracts\ExpenseTaxManager;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierPayment;
use App\Domains\Purchases\Models\SupplierRefund;
use App\Domains\Purchases\Policies\BillPolicy;
use App\Domains\Purchases\Policies\ExpenseCategoryPolicy;
use App\Domains\Purchases\Policies\ExpensePolicy;
use App\Domains\Purchases\Policies\SupplierCreditPolicy;
use App\Domains\Purchases\Policies\SupplierPaymentPolicy;
use App\Domains\Purchases\Policies\SupplierPolicy;
use App\Domains\Purchases\Policies\SupplierRefundPolicy;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PurchasesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExpenseTaxManager::class, TaxationExpenseTaxManager::class);
        $this->app->bind(ExpenseExchangeRateRecorder::class, MoneyExpenseExchangeRateRecorder::class);
        $this->app->bind(ExpenseReceiptManager::class, MediaLibraryExpenseReceiptManager::class);
    }

    public function boot(): void
    {
        Expense::observe(ClearExpenseTaxes::class);
        TaxType::observe(ProtectPurchaseTaxes::class);
        Expense::observe(ProtectCreditedExpenses::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Bill::class, BillPolicy::class);
        Gate::policy(SupplierPayment::class, SupplierPaymentPolicy::class);
        Gate::policy(SupplierCredit::class, SupplierCreditPolicy::class);
        Gate::policy(SupplierRefund::class, SupplierRefundPolicy::class);

        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
        $bulkExpenseDelete = [ExpensePolicy::class, 'deleteMultiple'];
        Gate::define('delete multiple expenses', $bulkExpenseDelete);
    }
}
