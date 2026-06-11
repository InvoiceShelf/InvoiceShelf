<?php

namespace App\Support;

/**
 * Recomputes authoritative invoice/estimate/recurring-invoice totals from the
 * submitted line items + document-level discount/taxes, mirroring the
 * front-end calculation (resources/scripts/admin/stores/invoice.js and
 * components/estimate-invoice-common/CreateItemRow.vue).
 *
 * The client-supplied total / sub_total / tax / due_amount are NOT trusted
 * (GHSA-8c69) — only price, quantity, discounts and tax-line amounts are.
 * All amounts are integer minor units (cents).
 */
class DocumentTotals
{
    /**
     * @param  array  $items  each item: price, quantity, discount_val?, taxes?[{amount}]
     * @param  array  $taxes  document-level taxes: [{amount}, ...]
     * @return array{sub_total:int, tax:int, total:int}
     */
    public static function compute(array $items, array $taxes, $discountVal, $taxPerItem, bool $taxIncluded, $discountPerItem = 'NO'): array
    {
        $perItemDiscount = is_string($discountPerItem) && strtoupper(trim($discountPerItem)) === 'YES';
        $perItemTax = is_string($taxPerItem) && strtoupper(trim($taxPerItem)) === 'YES';

        $subTotal = 0;
        $itemTaxTotal = 0;

        foreach ($items as $item) {
            $subTotal += self::itemTotal($item, $perItemDiscount);
            $itemTaxTotal += self::sumTaxAmounts($item['taxes'] ?? []);
        }

        $subtotalWithDiscount = $subTotal - (int) round((float) $discountVal);

        $totalTax = $perItemTax ? $itemTaxTotal : self::sumTaxAmounts($taxes);

        $total = $taxIncluded ? $subtotalWithDiscount : $subtotalWithDiscount + $totalTax;

        return [
            'sub_total' => $subTotal,
            'tax' => $totalTax,
            'total' => $total,
        ];
    }

    /**
     * Authoritative per-item total: round(price * quantity) minus the item
     * discount (only applied when discount is configured per item).
     */
    public static function itemTotal(array $item, bool $perItemDiscount): int
    {
        $price = (float) ($item['price'] ?? 0);
        $quantity = (float) ($item['quantity'] ?? 0);
        $discount = $perItemDiscount ? (int) round((float) ($item['discount_val'] ?? 0)) : 0;

        return (int) round($price * $quantity) - $discount;
    }

    protected static function sumTaxAmounts(array $taxes): int
    {
        $sum = 0;
        foreach ($taxes as $tax) {
            $sum += (int) round((float) ($tax['amount'] ?? 0));
        }

        return $sum;
    }
}
