<?php

namespace App\Support;

/**
 * Pure calculator for full and partial credit notes.
 *
 * A credit note reverses an invoice, either in full or one chunk of a line at a
 * time. The naive approach (compute each credit note from the quantities being
 * credited right now) drifts: three separate 1-unit credits of a 3-unit line
 * each round their own share of the discount and tax, and the three rounded
 * shares rarely add back up to what the invoice actually charged. This class
 * uses the cumulative-difference (telescoping) model instead:
 *
 *     credit(before -> after) = cumulative(after) - cumulative(before)
 *
 * where `cumulative()` answers a single question ("what would the credit be if
 * exactly these quantities had been credited, in one go?") and always derives
 * that answer from the ORIGINAL invoice's stored figures, never from previous
 * credit notes. Summing a chain of credits telescopes:
 *
 *     (c1 - c0) + (c2 - c1) + (c3 - c2) = c3 - c0 = c3
 *
 * so crediting a 3-unit line as 1+1+1 lands on exactly the same cents as
 * crediting it 3-at-once, in every field, and a fully credited invoice nets to
 * zero to the cent. The order the chunks are credited in does not matter
 * either, because only the endpoint quantities enter the arithmetic.
 *
 * Conventions:
 *
 * - **Integer minor units.** Every money field is an integer number of cents,
 *   as stored on the invoice, and every value returned here is an integer.
 * - **Quantities in hundredths.** `invoice_items.quantity` is `decimal(15,2)`,
 *   so quantities are carried as integer hundredths internally (2.50 -> 250)
 *   and only converted back to a decimal at the boundary. Float quantities
 *   never take part in a comparison or an accumulation.
 * - **Rounding.** `iround()` is `(int) round((float) $x)`, half away from zero,
 *   character for character the rule used by {@see DocumentTotals}, so a full
 *   credit lands on the same cents the invoice was written with.
 *
 * Two deliberate decisions in the tax handling:
 *
 * - **Fixed-amount tax rows are pro-rated exactly like percentage ones.** It is
 *   tempting to carry a flat 1.50 handling tax across in full on the first
 *   credit note, since it is not a function of the amount. But then crediting a
 *   3-unit line in three chunks charges back 1.50 three times, and the credited
 *   tax exceeds the tax the invoice ever collected. Pro-rating is what keeps
 *   the telescoping identity true for every tax row, which is the property the
 *   books balance on.
 * - **Percentage tax rows are pro-rated from the STORED amount, not
 *   re-derived.** The stored `taxes.amount` is what the customer was invoiced
 *   and what was reported; re-running `percent * base` on the credited slice
 *   re-does a rounding decision that was already made and can land a cent away
 *   from it, leaving a residue on a fully credited invoice. The stored integers
 *   are the ground truth.
 *
 * Base (company-currency) figures follow the same rule for the same reason:
 * they are pro-rated from the original's stored `base_*` integers rather than
 * recomputed through the exchange rate, so a full credit reproduces the stored
 * base amounts exactly instead of re-rounding the multiplication.
 *
 * This class is deliberately Eloquent-free and static: it takes a plain-array
 * snapshot of the original invoice and returns plain arrays. Negating the
 * magnitudes and persisting them is the calling service's job.
 */
class CreditNoteAmounts
{
    /**
     * Convert a decimal quantity to integer hundredths (2.50 -> 250).
     */
    public static function toHundredths($quantity): int
    {
        return (int) round((float) $quantity * 100);
    }

    /**
     * Convert integer hundredths back to a decimal quantity (250 -> 2.5).
     */
    public static function fromHundredths(int $hundredths): float
    {
        return $hundredths / 100;
    }

    /**
     * Compute the cumulative credit at the given credited quantities.
     *
     * The result is snapshot-shaped: it carries exactly the keys of the
     * snapshot minus the three configuration flags, so
     * `cumulative($snapshot, <full quantities>)` reproduces the snapshot field
     * for field, and `cumulative($snapshot, <all zeros>)` is all zeros.
     *
     * @param  array  $snapshot  the ORIGINAL invoice's stored figures:
     *                           sub_total, discount_val, tax, total, base_sub_total,
     *                           base_discount_val, base_tax, base_total,
     *                           discount_per_item ('YES'|'NO'), tax_per_item ('YES'|'NO'),
     *                           tax_included (bool),
     *                           items keyed by invoice_items.id, each with
     *                           price, quantity (decimal), discount_val, tax, total,
     *                           base_price, base_discount_val, base_tax, base_total and
     *                           taxes keyed by taxes.id, each with amount + base_amount,
     *                           taxes: document-level tax rows keyed by taxes.id
     * @param  array  $creditedHundredths  credited quantity in hundredths, keyed by
     *                                     invoice_items.id; missing items count as zero
     * @return array{sub_total:int, discount_val:int, tax:int, total:int, base_sub_total:int,
     *               base_discount_val:int, base_tax:int, base_total:int, items:array, taxes:array}
     */
    public static function cumulative(array $snapshot, array $creditedHundredths): array
    {
        $perItemDiscount = self::isYes($snapshot['discount_per_item'] ?? 'NO');
        $perItemTax = self::isYes($snapshot['tax_per_item'] ?? 'NO');
        $taxIncluded = (bool) ($snapshot['tax_included'] ?? false);

        $items = [];
        $subTotal = 0;
        $itemTaxTotal = 0;

        foreach ($snapshot['items'] ?? [] as $itemId => $item) {
            $originalHundredths = self::toHundredths($item['quantity'] ?? 0);
            $credited = (int) ($creditedHundredths[$itemId] ?? 0);
            $ratio = $originalHundredths === 0 ? 0.0 : $credited / $originalHundredths;

            $amount = self::iround((float) ($item['price'] ?? 0) * $credited / 100);
            $discount = $perItemDiscount
                ? self::iround((float) ($item['discount_val'] ?? 0) * $ratio)
                : 0;

            $taxes = [];
            $itemTax = 0;

            foreach ($item['taxes'] ?? [] as $taxId => $tax) {
                $taxAmount = self::iround((float) ($tax['amount'] ?? 0) * $ratio);

                $taxes[$taxId] = [
                    'amount' => $taxAmount,
                    'base_amount' => self::scale($tax['base_amount'] ?? 0, $taxAmount, $tax['amount'] ?? 0),
                ];

                $itemTax += $taxAmount;
            }

            $total = $amount - $discount;

            $items[$itemId] = [
                'price' => (int) ($item['price'] ?? 0),
                'quantity' => self::fromHundredths($credited),
                'discount_val' => $discount,
                'tax' => $itemTax,
                'total' => $total,
                'base_price' => (int) ($item['base_price'] ?? 0),
                'base_discount_val' => self::scale($item['base_discount_val'] ?? 0, $discount, $item['discount_val'] ?? 0),
                'base_tax' => self::scale($item['base_tax'] ?? 0, $itemTax, $item['tax'] ?? 0),
                'base_total' => self::scale($item['base_total'] ?? 0, $total, $item['total'] ?? 0),
                'taxes' => $taxes,
            ];

            $subTotal += $total;
            $itemTaxTotal += $itemTax;
        }

        $originalSubTotal = (int) ($snapshot['sub_total'] ?? 0);
        $ratio = $originalSubTotal === 0 ? 0.0 : $subTotal / $originalSubTotal;

        $discountVal = self::iround((float) ($snapshot['discount_val'] ?? 0) * $ratio);

        $documentTaxes = [];
        $documentTaxTotal = 0;

        foreach ($snapshot['taxes'] ?? [] as $taxId => $tax) {
            $taxAmount = self::iround((float) ($tax['amount'] ?? 0) * $ratio);

            $documentTaxes[$taxId] = [
                'amount' => $taxAmount,
                'base_amount' => self::scale($tax['base_amount'] ?? 0, $taxAmount, $tax['amount'] ?? 0),
            ];

            $documentTaxTotal += $taxAmount;
        }

        $tax = $perItemTax ? $itemTaxTotal : $documentTaxTotal;
        $total = $taxIncluded ? $subTotal - $discountVal : $subTotal - $discountVal + $tax;

        return [
            'sub_total' => $subTotal,
            'discount_val' => $discountVal,
            'tax' => $tax,
            'total' => $total,
            'base_sub_total' => self::scale($snapshot['base_sub_total'] ?? 0, $subTotal, $originalSubTotal),
            'base_discount_val' => self::scale($snapshot['base_discount_val'] ?? 0, $discountVal, $snapshot['discount_val'] ?? 0),
            'base_tax' => self::scale($snapshot['base_tax'] ?? 0, $tax, $snapshot['tax'] ?? 0),
            'base_total' => self::scale($snapshot['base_total'] ?? 0, $total, $snapshot['total'] ?? 0),
            'items' => $items,
            'taxes' => $documentTaxes,
        ];
    }

    /**
     * Compute one credit note: the field-wise difference between the cumulative
     * credit at $after and the cumulative credit at $before.
     *
     * Everything comes back as a POSITIVE magnitude. The caller negates what it
     * persists (a credit note stores negative prices, totals and tax amounts)
     * and copies the descriptive fields (item name, description, unit, tax
     * names, percentages, tax_type_id) from the original rows itself; only
     * amounts and quantities are decided here.
     *
     * `items` is keyed by the ORIGINAL invoice item id and only contains lines
     * whose credited quantity actually moved; a line credited by zero produces
     * no credit-note row.
     *
     * @param  array  $snapshot  the ORIGINAL invoice's stored figures, see {@see cumulative()}
     * @param  array  $before  already-credited quantities in hundredths, keyed by invoice_items.id
     * @param  array  $after  total credited quantities after this credit note, same keying
     * @return array{sub_total:int, discount_val:int, tax:int, total:int, base_sub_total:int,
     *               base_discount_val:int, base_tax:int, base_total:int,
     *               items:array<int, array{source_invoice_item_id:int, price:int, base_price:int,
     *               quantity:float, discount_val:int, tax:int, total:int, base_discount_val:int,
     *               base_tax:int, base_total:int, taxes:array<int, array{amount:int, base_amount:int}>}>,
     *               taxes:array<int, array{amount:int, base_amount:int}>}
     */
    public static function forCredit(array $snapshot, array $before, array $after): array
    {
        $from = self::cumulative($snapshot, $before);
        $to = self::cumulative($snapshot, $after);

        $items = [];

        foreach ($to['items'] as $itemId => $item) {
            $previous = $from['items'][$itemId];

            $hundredths = self::toHundredths($item['quantity']) - self::toHundredths($previous['quantity']);

            if ($hundredths === 0) {
                continue;
            }

            $taxes = [];

            foreach ($item['taxes'] as $taxId => $tax) {
                $taxes[$taxId] = [
                    'amount' => $tax['amount'] - $previous['taxes'][$taxId]['amount'],
                    'base_amount' => $tax['base_amount'] - $previous['taxes'][$taxId]['base_amount'],
                ];
            }

            $items[$itemId] = [
                'source_invoice_item_id' => (int) $itemId,
                'price' => $item['price'],
                'base_price' => $item['base_price'],
                'quantity' => self::fromHundredths($hundredths),
                'discount_val' => $item['discount_val'] - $previous['discount_val'],
                'tax' => $item['tax'] - $previous['tax'],
                'total' => $item['total'] - $previous['total'],
                'base_discount_val' => $item['base_discount_val'] - $previous['base_discount_val'],
                'base_tax' => $item['base_tax'] - $previous['base_tax'],
                'base_total' => $item['base_total'] - $previous['base_total'],
                'taxes' => $taxes,
            ];
        }

        $taxes = [];

        foreach ($to['taxes'] as $taxId => $tax) {
            $taxes[$taxId] = [
                'amount' => $tax['amount'] - $from['taxes'][$taxId]['amount'],
                'base_amount' => $tax['base_amount'] - $from['taxes'][$taxId]['base_amount'],
            ];
        }

        return [
            'sub_total' => $to['sub_total'] - $from['sub_total'],
            'discount_val' => $to['discount_val'] - $from['discount_val'],
            'tax' => $to['tax'] - $from['tax'],
            'total' => $to['total'] - $from['total'],
            'base_sub_total' => $to['base_sub_total'] - $from['base_sub_total'],
            'base_discount_val' => $to['base_discount_val'] - $from['base_discount_val'],
            'base_tax' => $to['base_tax'] - $from['base_tax'],
            'base_total' => $to['base_total'] - $from['base_total'],
            'items' => $items,
            'taxes' => $taxes,
        ];
    }

    /**
     * Pro-rate a stored base amount by the share its non-base counterpart got.
     *
     * A zero denominator means the original never carried the amount, so the
     * credited share of it is zero.
     */
    protected static function scale($value, $part, $whole): int
    {
        $whole = (float) $whole;

        if ($whole == 0.0) {
            return 0;
        }

        return self::iround((float) $value * (float) $part / $whole);
    }

    /**
     * Round to an integer, half away from zero, exactly as DocumentTotals does.
     */
    protected static function iround($value): int
    {
        return (int) round((float) $value);
    }

    protected static function isYes($flag): bool
    {
        return is_string($flag) && strtoupper(trim($flag)) === 'YES';
    }
}
