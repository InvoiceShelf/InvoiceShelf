<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Purchases\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bill */
class PurchaseLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'number' => (string) $this->number,
            'status' => (string) $this->status,
            'amount' => (int) ($this->amount ?? $this->total),
            'currency_id' => (int) $this->currency_id,
            'supplier_id' => (int) $this->supplier_id,
        ];
    }
}
