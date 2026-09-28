<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Purchases\Models\Bill;
use App\Support\CreditNoteAmounts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

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
            'attachments' => PurchaseAttachmentResource::collection(
                $this->whenLoaded('media', fn () => $this->attachments()),
            ),
            'creditable_quantities' => $this->whenLoaded('credits', fn () => $this->creditableQuantities()),
            'settlement_status' => (string) $this->settlement_status,
            'payment_allocations' => PurchaseAllocationResource::collection($this->whenLoaded('paymentAllocations')),
            'credit_allocations' => PurchaseAllocationResource::collection($this->whenLoaded('creditAllocations')),
            'credits' => PurchaseLinkResource::collection($this->whenLoaded('credits')),
        ];
    }

    /**
     * How much of each line is left to credit, by line id.
     *
     * Worked in integer hundredths, like the check that guards the credit, so
     * 1.00 less 0.70 and 0.10 is 0.2 and not 0.20000000000000007, which the
     * credit form would send back and the request would refuse. An object,
     * because the resource would re-index an array keyed by id into a list.
     */
    private function creditableQuantities(): object
    {
        $credited = $this->credits
            ->where('status', '!=', 'VOID')
            ->flatMap->items
            ->groupBy('source_bill_item_id')
            ->map(fn ($items) => $items->sum(fn ($item) => CreditNoteAmounts::toHundredths($item->quantity)));

        return (object) $this->items
            ->mapWithKeys(function ($item) use ($credited) {
                $left = CreditNoteAmounts::toHundredths($item->quantity) - ($credited[$item->id] ?? 0);

                return [$item->id => CreditNoteAmounts::fromHundredths(max(0, $left))];
            })
            ->all();
    }

    /**
     * The document's media in the purchase attachments collection.
     */
    private function attachments(): Collection
    {
        return $this->media->where('collection_name', 'purchase_documents')->values();
    }
}
