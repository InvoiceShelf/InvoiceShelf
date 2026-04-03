<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class DocumentItemService
{
    public function createItems(Model $document, array $items): void
    {
        $exchangeRate = $document->exchange_rate;

        foreach ($items as $item) {
            $item['company_id'] = $document->company_id;
            $item['exchange_rate'] = $exchangeRate;
            $item['base_price'] = $item['price'] * $exchangeRate;
            $item['base_discount_val'] = $item['discount_val'] * $exchangeRate;
            $item['base_tax'] = $item['tax'] * $exchangeRate;
            $item['base_total'] = $item['total'] * $exchangeRate;

            if (array_key_exists('recurring_invoice_id', $item)) {
                unset($item['recurring_invoice_id']);
            }

            $createdItem = $document->items()->create($item);

            if (array_key_exists('taxes', $item) && $item['taxes']) {
                foreach ($item['taxes'] as $tax) {
                    $tax['company_id'] = $document->company_id;
                    $tax['exchange_rate'] = $document->exchange_rate;
                    $tax['base_amount'] = $tax['amount'] * $exchangeRate;
                    $tax['currency_id'] = $document->currency_id;

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

    public function createTaxes(Model $document, array $taxes): void
    {
        $exchangeRate = $document->exchange_rate;

        foreach ($taxes as $tax) {
            $tax['company_id'] = $document->company_id;
            $tax['exchange_rate'] = $document->exchange_rate;
            $tax['base_amount'] = $tax['amount'] * $exchangeRate;
            $tax['currency_id'] = $document->currency_id;

            if (gettype($tax['amount']) !== 'NULL') {
                if (array_key_exists('recurring_invoice_id', $tax)) {
                    unset($tax['recurring_invoice_id']);
                }

                $document->taxes()->create($tax);
            }
        }
    }
}
