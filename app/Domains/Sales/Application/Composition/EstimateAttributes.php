<?php

namespace App\Domains\Sales\Application\Composition;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Estimate;
use App\Support\DocumentTotals;
use Illuminate\Support\Arr;

/**
 * The stored attributes of an estimate, built from what the estimate form
 * submits. The write request and the server-side composer both come through
 * here.
 */
final class EstimateAttributes
{
    /**
     * Totals are recomputed from the submitted lines, and the estimate is
     * denominated in the customer's currency.
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
            'status' => array_key_exists('estimateSend', $input) ? Estimate::STATUS_SENT : Estimate::STATUS_DRAFT,
            'company_id' => $companyId,
            'tax_per_item' => $perItemTax,
            'discount_per_item' => $perItemDiscount,
            'sub_total' => $sums['sub_total'],
            'total' => $sums['total'],
            'tax' => $sums['tax'],
            'exchange_rate' => $rate,
            'base_discount_val' => $discountVal * $rate,
            'base_sub_total' => $sums['sub_total'] * $rate,
            'base_total' => $sums['total'] * $rate,
            'base_tax' => $sums['tax'] * $rate,
            'currency_id' => Customer::find($input['customer_id'] ?? null)->currency_id,
        ]);
    }
}
