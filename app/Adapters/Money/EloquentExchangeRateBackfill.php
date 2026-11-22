<?php

namespace App\Adapters\Money;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Money\Contracts\ExchangeRateBackfill;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\Tax;
use App\Support\MoneyConversion;

/**
 * Coordinates the one-time exchange-rate backfill across durable document
 * tables while the Money context owns the workflow contract.
 *
 * It fills in documents written before documents carried an exchange rate, so
 * it touches one company's documents, and only those still without a rate:
 * other companies use the same currencies at their own rates, and a document
 * that has a rate already has the one it was issued with.
 */
class EloquentExchangeRateBackfill implements ExchangeRateBackfill
{
    public function currencyIdsMissingRates(int $companyId): array
    {
        return array_merge(
            Invoice::query()->where('company_id', $companyId)->whereNull('exchange_rate')->pluck('currency_id')->all(),
            Tax::query()->where('company_id', $companyId)->whereNull('exchange_rate')->pluck('currency_id')->all(),
            Estimate::query()->where('company_id', $companyId)->whereNull('exchange_rate')->pluck('currency_id')->all(),
            Payment::query()->where('company_id', $companyId)->whereNull('exchange_rate')->pluck('currency_id')->all(),
        );
    }

    public function apply(int $companyId, array $currencies): bool
    {
        if (CompanySetting::getSetting('bulk_exchange_rate_configured', $companyId) !== 'NO') {
            return false;
        }

        foreach ($currencies as $currency) {
            $rate = $currency['exchange_rate'] ?? 1;

            foreach (Invoice::query()->where('company_id', $companyId)->where('currency_id', $currency['id'])->whereNull('exchange_rate')->get() as $invoice) {
                $invoice->update([
                    'exchange_rate' => $rate,
                    'base_discount_val' => MoneyConversion::toBaseMinor($invoice->discount_val, $rate),
                    'base_sub_total' => MoneyConversion::toBaseMinor($invoice->sub_total, $rate),
                    'base_total' => MoneyConversion::toBaseMinor($invoice->total, $rate),
                    'base_tax' => MoneyConversion::toBaseMinor($invoice->tax, $rate),
                    'base_due_amount' => MoneyConversion::toBaseMinor($invoice->due_amount, $rate),
                ]);

                $this->updateItemsExchangeRate($invoice);
            }

            foreach (Estimate::query()->where('company_id', $companyId)->where('currency_id', $currency['id'])->whereNull('exchange_rate')->get() as $estimate) {
                $estimate->update([
                    'exchange_rate' => $rate,
                    'base_discount_val' => MoneyConversion::toBaseMinor($estimate->discount_val, $rate),
                    'base_sub_total' => MoneyConversion::toBaseMinor($estimate->sub_total, $rate),
                    'base_total' => MoneyConversion::toBaseMinor($estimate->total, $rate),
                    'base_tax' => MoneyConversion::toBaseMinor($estimate->tax, $rate),
                ]);

                $this->updateItemsExchangeRate($estimate);
            }

            foreach (Tax::query()->where('company_id', $companyId)->where('currency_id', $currency['id'])->whereNull('exchange_rate')->get() as $tax) {
                $tax->update([
                    'exchange_rate' => $rate,
                    'base_amount' => MoneyConversion::toBaseMinor($tax->amount, $rate),
                ]);
            }

            foreach (Payment::query()->where('company_id', $companyId)->where('currency_id', $currency['id'])->whereNull('exchange_rate')->get() as $payment) {
                $payment->update([
                    'exchange_rate' => $rate,
                    'base_amount' => MoneyConversion::toBaseMinor($payment->amount, $rate),
                ]);
            }
        }

        CompanySetting::setSettings([
            'bulk_exchange_rate_configured' => 'YES',
        ], $companyId);

        return true;
    }

    private function updateItemsExchangeRate(mixed $document): void
    {
        foreach ($document->items as $item) {
            $item->update([
                'exchange_rate' => $document->exchange_rate,
                'base_discount_val' => MoneyConversion::toBaseMinor($item->discount_val, $document->exchange_rate),
                'base_price' => MoneyConversion::toBaseMinor($item->price, $document->exchange_rate),
                'base_tax' => MoneyConversion::toBaseMinor($item->tax, $document->exchange_rate),
                'base_total' => MoneyConversion::toBaseMinor($item->total, $document->exchange_rate),
            ]);

            $this->updateTaxesExchangeRate($item);
        }

        $this->updateTaxesExchangeRate($document);
    }

    private function updateTaxesExchangeRate(mixed $taxable): void
    {
        if (! $taxable->taxes()->exists()) {
            return;
        }

        $taxable->taxes->each(function ($tax) use ($taxable): void {
            $tax->update([
                'exchange_rate' => $taxable->exchange_rate,
                'base_amount' => MoneyConversion::toBaseMinor($tax->amount, $taxable->exchange_rate),
            ]);
        });
    }
}
