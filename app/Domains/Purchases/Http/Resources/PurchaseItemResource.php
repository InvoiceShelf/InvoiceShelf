<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Purchases\Models\BillItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BillItem */
class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'description' => (string) $this->description,
            'expense_category_id' => (int) $this->expense_category_id,
            'quantity' => (float) $this->quantity,
            'price' => (int) $this->price,
            'discount' => (float) $this->discount,
            'discount_val' => (int) $this->discount_val,
            'sub_total' => (int) $this->sub_total,
            'tax' => (int) $this->tax,
            'total' => (int) $this->total,
            'base_total' => (int) $this->base_total,
            'source_bill_item_id' => $this->source_bill_item_id === null ? null : (int) $this->source_bill_item_id,
            'taxes' => $this->taxes ?? [],
            'tax_type_ids' => array_column($this->taxes ?? [], 'tax_type_id'),
        ];
    }
}
