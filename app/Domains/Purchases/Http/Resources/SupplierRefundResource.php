<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Purchases\Models\SupplierRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierRefund */
class SupplierRefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'supplier_id' => (int) $this->supplier_id,
            'currency_id' => (int) $this->currency_id,
            'exchange_rate' => (float) $this->exchange_rate,
            'number' => (string) $this->number,
            'reference' => $this->reference === null ? null : (string) $this->reference,
            'payment_date' => (string) $this->payment_date,
            'amount' => (int) $this->amount,
            'base_amount' => (int) $this->base_amount,
            'payment_method_id' => $this->payment_method_id === null ? null : (int) $this->payment_method_id,
            'status' => (string) $this->status,
            'notes' => $this->notes === null ? null : (string) $this->notes,
            'supplier_payment_id' => $this->supplier_payment_id === null ? null : (int) $this->supplier_payment_id,
            'supplier_credit_id' => $this->supplier_credit_id === null ? null : (int) $this->supplier_credit_id,
            'void_reason' => $this->void_reason === null ? null : (string) $this->void_reason,
            'voided_at' => $this->voided_at === null ? null : (string) $this->voided_at,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'activities' => PurchaseActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
