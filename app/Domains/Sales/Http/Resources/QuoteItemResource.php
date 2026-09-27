<?php

namespace App\Domains\Sales\Http\Resources;

use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Taxation\Http\Resources\TaxResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a quote as the admin API publishes it.
 *
 * Same snapshot idea as the invoice line -- figures stored on the row, the
 * catalogue item only referenced by id, every amount paired with its `base_`
 * twin at the document's exchange rate -- but the ordering of the quantity and
 * price fields follows the quote payload the SPA already expects, which is
 * not the ordering used by the invoice line.
 */
class QuoteItemResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $item = $this->resource;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'discount_type' => $item->discount_type,
            'quantity' => $item->quantity,
            'unit_name' => $item->unit_name,
            'discount' => $item->discount,
            'discount_val' => $item->discount_val,
            'price' => $item->price,
            'tax' => $item->tax,
            'total' => $item->total,
            'item_id' => $item->item_id,
            'quote_id' => $item->quote_id,
            'company_id' => $item->company_id,
            'exchange_rate' => $item->exchange_rate,
            'base_discount_val' => $item->base_discount_val,
            'base_price' => $item->base_price,
            'base_tax' => $item->base_tax,
            'base_total' => $item->base_total,
            'taxes' => $this->when(
                $item->taxes()->exists(),
                fn () => TaxResource::collection($item->taxes)
            ),
            'fields' => $this->when(
                $item->fields()->exists(),
                fn () => CustomFieldValueResource::collection($item->fields)
            ),
        ];
    }
}
