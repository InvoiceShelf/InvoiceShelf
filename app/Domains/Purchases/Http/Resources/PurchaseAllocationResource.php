<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Purchases\Models\SupplierPaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierPaymentAllocation */
class PurchaseAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'bill_id' => (int) $this->bill_id,
            'amount' => (int) $this->amount,
            'base_amount' => (int) $this->base_amount,
            'bill' => new PurchaseLinkResource($this->whenLoaded('bill')),
            'payment' => new PurchaseLinkResource($this->whenLoaded('payment')),
            'credit' => new PurchaseLinkResource($this->whenLoaded('credit')),
        ];
    }
}
