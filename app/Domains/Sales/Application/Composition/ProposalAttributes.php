<?php

namespace App\Domains\Sales\Application\Composition;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Estimate;
use App\Support\DocumentTotals;
use App\Support\MoneyConversion;
use Illuminate\Support\Arr;

/**
 * The stored attributes of an estimate, built from what the estimate form
 * submits. The write request and the server-side composer both come through
 * here.
 */
final class ProposalAttributes
{
    /**
     * Totals are recomputed from the submitted lines, and the estimate is
     * denominated in the customer's currency.
     *
     * @param  array<string, mixed>  $input  the form's submission; items, taxes and custom fields are stored apart
     * @return array<string, mixed>
     */
    public static function fromInput(array $input, int|string|null $companyId, ?int $creatorId, string $kind = 'estimate'): array
    {
        $customer = Customer::findOrFail($input['customer_id'] ?? null);
        $rate = CompanySetting::getSetting('currency', $companyId) != $customer->currency_id
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

        return array_merge(Arr::only($input, [$kind.'_date', $kind.'_number', 'expiry_date', 'customer_id', 'currency_id', 'reference_number', 'template_name', 'notes', 'discount', 'discount_type', 'discount_val', 'tax_included', 'sales_tax_type', 'sales_tax_address_type']), [
            'creator_id' => $creatorId,
            'status' => array_key_exists($kind.'Send', $input) ? Estimate::STATUS_SENT : Estimate::STATUS_DRAFT,
            'company_id' => $companyId,
            'tax_per_item' => $perItemTax,
            'discount_per_item' => $perItemDiscount,
            'sub_total' => $sums['sub_total'],
            'total' => $sums['total'],
            'tax' => $sums['tax'],
            'exchange_rate' => $rate,
            'base_discount_val' => MoneyConversion::toBaseMinor($discountVal, $rate),
            'base_sub_total' => MoneyConversion::toBaseMinor($sums['sub_total'], $rate),
            'base_total' => MoneyConversion::toBaseMinor($sums['total'], $rate),
            'base_tax' => MoneyConversion::toBaseMinor($sums['tax'], $rate),
            'currency_id' => $customer->currency_id,
        ]);
    }
}
