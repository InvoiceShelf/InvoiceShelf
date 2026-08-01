<?php

namespace App\Services\Document;

use App\Support\DocumentTotals;
use Illuminate\Database\Eloquent\Model;

class DocumentItemService
{
    /**
     * Company-currency columns and the column each one is derived from.
     */
    private const BASE_FIELDS = [
        'base_price' => 'price',
        'base_discount_val' => 'discount_val',
        'base_tax' => 'tax',
        'base_total' => 'total',
    ];

    /**
     * Persist the line items of a document.
     *
     * $recompute = false is for callers that have already decided every cent of
     * the document and must not have it decided again: partial credit notes
     * carry pro-rated integers from CreditNoteAmounts, and re-deriving the line
     * total from price * quantity or the base_* columns from the exchange rate
     * rounds a second time, drifts a cent, and breaks the telescoping invariant
     * that makes a chain of partial credits add back up to the invoice. Such a
     * caller still gets a base_* value derived here for any it did not supply.
     */
    public function createItems(Model $document, array $items, bool $recompute = true): void
    {
        $exchangeRate = $document->exchange_rate;

        foreach ($items as $item) {
            $item['company_id'] = $document->company_id;
            $item['exchange_rate'] = $exchangeRate;

            if ($recompute) {
                // Recompute the item total from price/quantity so a tampered item
                // total can't desync from the recomputed document totals (GHSA-8c69).
                $item['total'] = DocumentTotals::itemTotal($item, $document->discount_per_item === 'YES');
                $item['base_price'] = $item['price'] * $exchangeRate;
                $item['base_discount_val'] = $item['discount_val'] * $exchangeRate;
                $item['base_tax'] = $item['tax'] * $exchangeRate;
                $item['base_total'] = $item['total'] * $exchangeRate;
            } else {
                foreach (self::BASE_FIELDS as $baseField => $field) {
                    if (! array_key_exists($baseField, $item)) {
                        $item[$baseField] = ($item[$field] ?? 0) * $exchangeRate;
                    }
                }
            }

            if (array_key_exists('recurring_invoice_id', $item)) {
                unset($item['recurring_invoice_id']);
            }

            $createdItem = $document->items()->create($item);

            if (array_key_exists('taxes', $item) && $item['taxes']) {
                foreach ($item['taxes'] as $tax) {
                    $tax['company_id'] = $document->company_id;
                    $tax['exchange_rate'] = $document->exchange_rate;
                    $tax['currency_id'] = $document->currency_id;

                    if ($recompute || ! array_key_exists('base_amount', $tax)) {
                        $tax['base_amount'] = $tax['amount'] * $exchangeRate;
                    }

                    if (gettype($tax['amount']) !== 'NULL') {
                        if (array_key_exists('recurring_invoice_id', $tax)) {
                            unset($tax['recurring_invoice_id']);
                        }

                        $createdItem->taxes()->create($tax);
                    }
                }
            }

            if (array_key_exists('custom_fields', $item) && $item['custom_fields']) {
                $createdItem->addCustomFields($item['custom_fields']);
            }
        }
    }

    /**
     * Persist the document-level tax rows.
     *
     * $recompute = false has the same meaning as in {@see createItems()}: the
     * supplied base_amount is the caller's pro-rated integer and is kept as-is.
     */
    public function createTaxes(Model $document, array $taxes, bool $recompute = true): void
    {
        $exchangeRate = $document->exchange_rate;

        foreach ($taxes as $tax) {
            $tax['company_id'] = $document->company_id;
            $tax['exchange_rate'] = $document->exchange_rate;
            $tax['currency_id'] = $document->currency_id;

            if ($recompute || ! array_key_exists('base_amount', $tax)) {
                $tax['base_amount'] = $tax['amount'] * $exchangeRate;
            }

            if (gettype($tax['amount']) !== 'NULL') {
                if (array_key_exists('recurring_invoice_id', $tax)) {
                    unset($tax['recurring_invoice_id']);
                }

                $document->taxes()->create($tax);
            }
        }
    }
}
