<?php

namespace App\Domains\Sales\Application\Composition;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use App\Support\DocumentTotals;
use App\Support\MoneyConversion;
use Illuminate\Support\Arr;

/**
 * The stored attributes of an invoice, built from what the invoice form
 * submits. The write request and the server-side composer both come through
 * here, so an invoice is stored the same way whoever wrote it.
 */
final class InvoiceAttributes
{
    /**
     * Totals are recomputed from the submitted lines (GHSA-8c69): whatever
     * sub_total / total / tax the client sent is discarded. The document is
     * always denominated in the customer's currency, and it is never allowed
     * to declare itself a credit note: those are minted by the credit-note
     * service alone.
     *
     * @param  array<string, mixed>  $input  the form's submission; items, taxes and custom fields are stored apart
     * @return array<string, mixed>
     */
    public static function fromInput(array $input, int|string|null $companyId, ?int $creatorId): array
    {
        $rate = CompanySetting::getSetting('currency', $companyId) != ($input['currency_id'] ?? null)
            ? ($input['exchange_rate'] ?? null)
            : 1;

        $perItemTax = CompanySetting::getSetting('tax_per_item', $companyId) ?? 'NO';
        $perItemDiscount = CompanySetting::getSetting('discount_per_item', $companyId) ?? 'NO';
        $discountVal = $input['discount_val'] ?? null;

        $sums = DocumentTotals::compute(
            $input['items'] ?? [],
            $input['taxes'] ?? [],
            $discountVal,
            $perItemTax,
            (bool) ($input['tax_included'] ?? false),
            $perItemDiscount
        );

        return array_merge(Arr::except($input, ['items', 'taxes', 'customFields']), [
            'creator_id' => $creatorId,
            'type' => Invoice::TYPE_INVOICE,
            'related_invoice_id' => null,
            'credit_reason' => null,
            'status' => array_key_exists('invoiceSend', $input) ? Invoice::STATUS_SENT : Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'company_id' => $companyId,
            'tax_per_item' => $perItemTax,
            'discount_per_item' => $perItemDiscount,
            'sub_total' => $sums['sub_total'],
            'total' => $sums['total'],
            'tax' => $sums['tax'],
            'due_amount' => $sums['total'],
            'sent' => (bool) ($input['sent'] ?? false),
            'viewed' => (bool) ($input['viewed'] ?? false),
            'exchange_rate' => $rate,
            'base_total' => MoneyConversion::toBaseMinor($sums['total'], $rate),
            'base_discount_val' => MoneyConversion::toBaseMinor($discountVal, $rate),
            'base_sub_total' => MoneyConversion::toBaseMinor($sums['sub_total'], $rate),
            'base_tax' => MoneyConversion::toBaseMinor($sums['tax'], $rate),
            'base_due_amount' => MoneyConversion::toBaseMinor($sums['total'], $rate),
            'currency_id' => Customer::find($input['customer_id'] ?? null)->currency_id,
        ]);
    }
}
