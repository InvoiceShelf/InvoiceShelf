<?php

namespace App\Support;

/**
 * The document form's arithmetic, done on the server: line subtotals and
 * discounts, the document discount, and every tax row's amount.
 *
 * This is a port of the SPA, not a new model of tax. Whatever the invoice and
 * estimate forms would have computed for the same input, this computes too,
 * cent for cent, quirks included, so a document composed here reads exactly
 * like one typed into the form. The sources, which must change together with
 * this class:
 *
 * - `resources/scripts/features/shared/document-form/use-document-calculations.ts`
 *   (`calcItemSubtotal`, `calcItemDiscountVal`, `calcTaxAmount`)
 * - `DocumentItemRow.vue` (`setDiscount`, the line tax totals)
 * - `DocumentItemRowTax.vue` (`effectiveBase`, a line's share of a document discount)
 * - `DocumentTotals.vue` (`setDiscount`, `recalculateGlobalTaxes`)
 *
 * The quirks that are kept on purpose:
 *
 * - a fixed tax is charged once per row, whatever the quantity;
 * - an inclusive compound tax is added on top instead of being backed out;
 * - a line's share of a document discount is its proportion of the lines
 *   rounded to two decimals first;
 * - rounding is JavaScript's `Math.round`, half toward positive infinity.
 *
 * The stored sums still come from {@see DocumentTotals}, which the write
 * requests apply to whatever they are sent.
 *
 * Money is integer minor units. A fixed discount is given the way the form
 * holds it, in major units (5 is 5.00), and a percentage one as a percent.
 *
 * @phpstan-type TaxRate array{tax_type_id: int, name?: string, percent: float|int|string|null, calculation_type: string|null, fixed_amount: int|null, compound_tax: bool}
 * @phpstan-type Line array{price: int, quantity: float|int, discount?: float|int, discount_type?: string, taxes?: list<TaxRate>}
 */
final class DocumentTaxes
{
    /**
     * Compose every derived amount of a document.
     *
     * @param  list<Line>  $lines
     * @param  list<TaxRate>  $taxes  document-level tax rows, ignored when taxes are per line
     * @return array{
     *     lines: list<array{sub_total: int, discount_val: int, total: int, tax: int, taxes: list<array<string, mixed>>}>,
     *     taxes: list<array<string, mixed>>,
     *     discount_val: int,
     *     sub_total: int,
     *     tax: int,
     *     total: int,
     * }
     */
    public static function compose(
        array $lines,
        array $taxes,
        float|int $discount,
        string $discountType,
        bool $taxPerItem,
        bool $discountPerItem,
        bool $taxIncluded,
    ): array {
        $composed = [];

        foreach ($lines as $line) {
            $subTotal = self::itemSubtotal($line['price'], $line['quantity']);
            $discountVal = $discountPerItem
                ? self::itemDiscountVal($subTotal, $line['discount'] ?? 0, $line['discount_type'] ?? 'fixed')
                : 0;

            $composed[] = [
                'sub_total' => $subTotal,
                'discount_val' => $discountVal,
                'total' => $subTotal - $discountVal,
                'tax' => 0,
                'taxes' => [],
            ];
        }

        $itemsTotal = array_sum(array_column($composed, 'total'));
        $documentDiscount = $discountPerItem ? 0 : $discount;

        if ($taxPerItem) {
            foreach ($lines as $index => $line) {
                $base = self::lineBase($composed[$index]['total'], $itemsTotal, $documentDiscount, $discountType, $discountPerItem);
                $rows = self::chargeTaxes($line['taxes'] ?? [], $base, $taxIncluded);

                $composed[$index]['taxes'] = $rows;
                $composed[$index]['tax'] = array_sum(array_column($rows, 'amount'));
            }
        }

        $discountVal = self::documentDiscountVal($itemsTotal, $documentDiscount, $discountType);
        $documentTaxes = $taxPerItem ? [] : self::chargeTaxes($taxes, $itemsTotal - $discountVal, $taxIncluded);

        $taxRows = $taxPerItem ? array_merge([], ...array_column($composed, 'taxes')) : $documentTaxes;
        $simple = self::sumAmounts($taxRows, false);
        $compound = self::sumAmounts($taxRows, true);
        $subtotalWithDiscount = $itemsTotal - $discountVal;

        return [
            'lines' => $composed,
            'taxes' => $documentTaxes,
            'discount_val' => $discountVal,
            'sub_total' => $itemsTotal,
            'tax' => $simple + $compound,
            'total' => $taxIncluded
                ? $subtotalWithDiscount + $compound
                : $subtotalWithDiscount + $simple + $compound,
        ];
    }

    /**
     * `calcItemSubtotal`: price times quantity, rounded.
     */
    public static function itemSubtotal(int $price, float|int $quantity): int
    {
        return self::round($price * $quantity);
    }

    /**
     * `calcItemDiscountVal` / `DocumentItemRow.setDiscount`. A fixed discount
     * never exceeds the line; both kinds are taken off the line's magnitude.
     */
    public static function itemDiscountVal(int $subTotal, float|int $discount, string $discountType): int
    {
        $absolute = abs($subTotal);

        if ($discountType === 'percentage') {
            return self::round(($absolute * $discount) / 100);
        }

        return min(self::round($discount * 100), $absolute);
    }

    /**
     * `DocumentTotals.setDiscount`: the document discount in minor units.
     */
    public static function documentDiscountVal(int $subTotal, float|int $discount, string $discountType): int
    {
        if ($discountType === 'percentage') {
            return self::round(($subTotal * $discount) / 100);
        }

        return self::round($discount * 100);
    }

    /**
     * `DocumentItemRowTax.effectiveBase`: what a line's taxes are charged on.
     *
     * A per-line discount is already inside the line total. A document
     * discount is shared out by each line's proportion of all lines, and that
     * proportion is rounded to two decimals before it is used.
     */
    public static function lineBase(int $lineTotal, int $itemsTotal, float|int $documentDiscount, string $discountType, bool $discountPerItem): int
    {
        if ($discountPerItem || $documentDiscount <= 0 || ! $itemsTotal) {
            return $lineTotal;
        }

        $proportion = self::toFixed2($lineTotal / $itemsTotal);
        $discount = $discountType === 'fixed'
            ? $documentDiscount * 100
            : ($itemsTotal * $documentDiscount) / 100;

        return $lineTotal - self::round($discount * $proportion);
    }

    /**
     * `calcTaxAmount`: one tax row's amount.
     *
     * A fixed tax is its flat amount. Otherwise an exclusive simple tax is the
     * percent of the base, an inclusive one is backed out of it, and a
     * compound tax is the percent of the base plus the simple taxes when
     * exclusive, or of the base alone when inclusive.
     */
    public static function taxAmount(
        float|int $base,
        float|int|string|null $percent,
        ?int $fixedAmount,
        ?string $calculationType,
        bool $taxIncluded,
        bool $compoundTax = false,
        int $simpleTaxTotal = 0,
    ): int {
        if ($calculationType === 'fixed' && $fixedAmount !== null) {
            return $fixedAmount;
        }

        $percent = (float) $percent;

        if (! $base || ! $percent) {
            return 0;
        }

        if ($compoundTax) {
            if ($taxIncluded) {
                return self::round(($base * $percent) / 100);
            }

            return self::round((($base + $simpleTaxTotal) * $percent) / 100);
        }

        if ($taxIncluded) {
            return self::round($base - $base / (1 + $percent / 100));
        }

        return self::round(($base * $percent) / 100);
    }

    /**
     * JavaScript's `Math.round`: the nearest integer, a half going up.
     */
    public static function round(float|int $value): int
    {
        $floor = floor($value);

        return (int) ($value - $floor >= 0.5 ? $floor + 1 : $floor);
    }

    /**
     * `parseFloat(value.toFixed(2))`.
     *
     * `sprintf` rounds the exact binary value correctly, as `toFixed` does,
     * except on an exact tie, which it settles to the even digit where
     * `toFixed` goes away from zero. A tie at two decimals is a multiple of an
     * eighth, so those are handled apart.
     */
    public static function toFixed2(float|int $value): float
    {
        $eighths = abs($value) * 8;

        if (floor($eighths) === $eighths && fmod($eighths, 2.0) === 1.0) {
            $hundredths = abs($value) * 100 + 0.5;

            return ($value < 0 ? -$hundredths : $hundredths) / 100;
        }

        return (float) sprintf('%.2f', $value);
    }

    /**
     * Charge a set of tax rows on one base: the simple ones first, fixed
     * included, then the compound ones on top of their sum. The rows keep
     * their order.
     *
     * @param  list<TaxRate>  $rates
     * @return list<array<string, mixed>>
     */
    private static function chargeTaxes(array $rates, int $base, bool $taxIncluded): array
    {
        $rows = [];
        $simpleTotal = 0;

        foreach ($rates as $index => $rate) {
            if (self::isCompound($rate)) {
                continue;
            }

            $rows[$index] = $rate;
            $rows[$index]['amount'] = self::taxAmount(
                $base,
                $rate['percent'] ?? null,
                $rate['fixed_amount'] ?? null,
                $rate['calculation_type'] ?? null,
                $taxIncluded,
            );
            $simpleTotal += $rows[$index]['amount'];
        }

        foreach ($rates as $index => $rate) {
            if (! self::isCompound($rate)) {
                continue;
            }

            $rows[$index] = $rate;
            $rows[$index]['amount'] = self::taxAmount(
                $base,
                $rate['percent'] ?? null,
                $rate['fixed_amount'] ?? null,
                $rate['calculation_type'] ?? null,
                $taxIncluded,
                true,
                $simpleTotal,
            );
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function sumAmounts(array $rows, bool $compound): int
    {
        $sum = 0;

        foreach ($rows as $row) {
            if (self::isCompound($row) === $compound) {
                $sum += $row['amount'];
            }
        }

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $rate
     */
    private static function isCompound(array $rate): bool
    {
        return (bool) ($rate['compound_tax'] ?? false);
    }
}
