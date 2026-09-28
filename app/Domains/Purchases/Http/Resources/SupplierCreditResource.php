<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Purchases\Models\SupplierCredit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierCredit */
class SupplierCreditResource extends JsonResource
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
            'document_date' => (string) $this->document_date,
            'tax_included' => (bool) $this->tax_included,
            'status' => (string) $this->status,
            'sub_total' => (int) $this->sub_total,
            'tax' => (int) $this->tax,
            'total' => (int) $this->total,
            'base_total' => (int) $this->base_total,
            'notes' => $this->notes === null ? null : (string) $this->notes,
            'source_bill_id' => $this->source_bill_id === null ? null : (int) $this->source_bill_id,
            'source_expense_id' => $this->source_expense_id === null ? null : (int) $this->source_expense_id,
            'void_reason' => $this->void_reason === null ? null : (string) $this->void_reason,
            'voided_at' => $this->voided_at === null ? null : (string) $this->voided_at,
            'supplier_snapshot' => (object) ($this->supplier_snapshot ?? []),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
            'attachments' => PurchaseAttachmentResource::collection($this->whenLoaded('media', fn () => $this->media->where('collection_name', 'purchase_documents')->values())),
            'available_amount' => (int) $this->available_amount,
            'allocations' => PurchaseAllocationResource::collection($this->whenLoaded('allocations')),
            'refunds' => SupplierRefundResource::collection($this->whenLoaded('refunds')),
            'activities' => PurchaseActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
