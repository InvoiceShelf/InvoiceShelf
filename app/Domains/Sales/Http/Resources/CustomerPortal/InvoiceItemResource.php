<?php

namespace App\Domains\Sales\Http\Resources\CustomerPortal;

use App\Domains\Metadata\Http\Resources\CustomerPortal\CustomFieldValueResource;
use App\Domains\Taxation\Http\Resources\CustomerPortal\TaxResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of an invoice as the customer portal publishes it.
 *
 * The same line fields as the admin view -- the row is already a snapshot, so
 * there is nothing internal to hold back -- with the line's taxes and custom
 * field values published through the portal variants of those resources.
 */
class InvoiceItemResource extends JsonResource
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
            'price' => $item->price,
            'quantity' => $item->quantity,
            'unit_name' => $item->unit_name,
            'discount' => $item->discount,
            'discount_val' => $item->discount_val,
            'tax' => $item->tax,
            'total' => $item->total,
            'invoice_id' => $item->invoice_id,
            'item_id' => $item->item_id,
            'company_id' => $item->company_id,
            'base_price' => $item->base_price,
            'exchange_rate' => $item->exchange_rate,
            'base_discount_val' => $item->base_discount_val,
            'base_tax' => $item->base_tax,
            'base_total' => $item->base_total,
            'recurring_invoice_id' => $item->recurring_invoice_id,
            'taxes' => $this->when(
                $item->taxes()->exists(),
                fn () => TaxResource::collection($item->taxes)
            ),
            'fields' => $this->when(
                $item->printedFields()->exists(),
                fn () => CustomFieldValueResource::collection($item->printedFields)
            ),
        ];
    }
}
