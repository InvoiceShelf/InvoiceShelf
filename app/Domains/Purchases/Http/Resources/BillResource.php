<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Purchases\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bill */
class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'fields' => CustomFieldValueResource::collection($this->whenLoaded('fields')),
            'supplier_id' => (int) $this->supplier_id,
            'currency_id' => (int) $this->currency_id,
            'exchange_rate' => (float) $this->exchange_rate,
            'number' => (string) $this->number,
            'reference' => $this->reference === null ? null : (string) $this->reference,
            'document_date' => (string) $this->document_date,
            'due_date' => (string) $this->due_date,
            'tax_included' => (bool) $this->tax_included,
            'status' => (string) $this->status,
            'sub_total' => (int) $this->sub_total,
            'tax' => (int) $this->tax,
            'total' => (int) $this->total,
            'base_total' => (int) $this->base_total,
            'due_amount' => (int) $this->due_amount,
            'base_due_amount' => (int) $this->base_due_amount,
            'financial_locked_at' => $this->financial_locked_at === null ? null : (string) $this->financial_locked_at,
            'notes' => $this->notes === null ? null : (string) $this->notes,
            'void_reason' => $this->void_reason === null ? null : (string) $this->void_reason,
            'voided_at' => $this->voided_at === null ? null : (string) $this->voided_at,
            'supplier_snapshot' => (object) ($this->supplier_snapshot ?? []),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
            'attachments' => PurchaseAttachmentResource::collection($this->whenLoaded('media', fn () => $this->media->where('collection_name', 'purchase_documents')->values())),
            'creditable_quantities' => $this->whenLoaded('credits', fn () => $this->creditableQuantities()),
            'settlement_status' => (string) $this->settlement_status,
            'payment_allocations' => PurchaseAllocationResource::collection($this->whenLoaded('paymentAllocations')),
            'credit_allocations' => PurchaseAllocationResource::collection($this->whenLoaded('creditAllocations')),
            'credits' => PurchaseLinkResource::collection($this->whenLoaded('credits')),
            'activities' => PurchaseActivityResource::collection($this->whenLoaded('activities')),
        ];
    }

    /** @return array<string, float> */
    private function creditableQuantities(): array
    {
        return $this->items->mapWithKeys(fn ($item) => [$item->id => max(0, (float) $item->quantity - $this->credits->where('status', '!=', 'VOID')->flatMap->items->where('source_bill_item_id', $item->id)->sum('quantity'))])->all();
    }
}
