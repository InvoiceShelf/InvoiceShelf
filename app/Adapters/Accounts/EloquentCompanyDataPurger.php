<?php

namespace App\Adapters\Accounts;

use App\Domains\Accounts\Contracts\CompanyDataPurger;
use App\Domains\Accounts\Models\Company;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Application\QuoteService;
use Illuminate\Database\Eloquent\Model;

class EloquentCompanyDataPurger implements CompanyDataPurger
{
    public function purge(Company $company): void
    {

        $company->quotes->each(fn ($quote) => app(QuoteService::class)->delete($quote));
        $company->exchangeRateLogs()->delete();
        $company->exchangeRateProviders()->delete();
        $company->expenses->each->delete();
        $company->expenseCategories()->delete();

        PaymentAllocation::query()
            ->whereIn('payment_id', $company->payments()->select('id'))
            ->delete();
        $company->payments()->delete();
        $company->paymentMethods()->delete();
        $company->customFieldValues()->delete();
        $company->customFields()->delete();

        $company->invoices->each(function (Model $invoice): void {
            $this->clearDocumentData($invoice);
            $invoice->transactions()->delete();
        });
        $company->invoices()->delete();

        $company->recurringInvoices->each(fn (Model $invoice) => $this->clearDocumentData($invoice));
        $company->recurringInvoices()->delete();

        $company->estimates->each(fn (Model $estimate) => $this->clearDocumentData($estimate));
        $company->estimates()->delete();

        $company->items()->delete();
        $company->taxTypes()->delete();

        $company->customers->each(function (Model $customer): void {
            $customer->addresses()->delete();
            $customer->delete();
        });

        $company->address()->delete();
    }

    private function clearDocumentData(Model $document): void
    {
        $document->items->each(function (Model $item): void {
            $item->taxes()->delete();
            $item->delete();
        });

        $document->taxes()->delete();
    }
}
