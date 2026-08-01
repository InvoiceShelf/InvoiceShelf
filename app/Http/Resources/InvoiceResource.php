<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'invoice_number' => $this->invoice_number,
            'reference_number' => $this->reference_number,
            'type' => $this->type,
            'related_invoice_id' => $this->related_invoice_id,
            'status' => $this->status,
            'paid_status' => $this->paid_status,
            'tax_per_item' => $this->tax_per_item,
            'tax_included' => $this->tax_included,
            'discount_per_item' => $this->discount_per_item,
            'notes' => $this->notes,
            'discount_type' => $this->discount_type,
            'discount' => $this->discount,
            'discount_val' => $this->discount_val,
            'sub_total' => $this->sub_total,
            'total' => $this->total,
            'tax' => $this->tax,
            'due_amount' => $this->due_amount,
            'sent' => $this->sent,
            'viewed' => $this->viewed,
            'unique_hash' => $this->unique_hash,
            'template_name' => $this->template_name,
            'customer_id' => $this->customer_id,
            'recurring_invoice_id' => $this->recurring_invoice_id,
            'sequence_number' => $this->sequence_number,
            'exchange_rate' => $this->exchange_rate,
            'base_discount_val' => $this->base_discount_val,
            'base_sub_total' => $this->base_sub_total,
            'base_total' => $this->base_total,
            'creator_id' => $this->creator_id,
            'base_tax' => $this->base_tax,
            'base_due_amount' => $this->base_due_amount,
            'currency_id' => $this->currency_id,
            'formatted_created_at' => $this->formattedCreatedAt,
            'invoice_pdf_url' => $this->invoicePdfUrl,
            'formatted_invoice_date' => $this->formattedInvoiceDate,
            'formatted_due_date' => $this->formattedDueDate,
            'allow_edit' => $this->allow_edit,
            'payment_module_enabled' => $this->payment_module_enabled,
            'sales_tax_type' => $this->sales_tax_type,
            'sales_tax_address_type' => $this->sales_tax_address_type,
            'overdue' => $this->overdue,
            // Credit notes reversing this invoice (minimal reference so the
            // UI can flag the invoice as cancelled and link to the storno
            // document, mirroring the related_invoice back-link). Emitted only
            // where the relation was eager-loaded: probing it per row costs two
            // queries each, and this resource is serialized in paginated lists.
            'credit_notes' => $this->when(
                $this->relationLoaded('creditNotes') && $this->creditNotes->isNotEmpty(),
                fn () => $this->creditNotes->map(fn ($creditNote) => [
                    'id' => $creditNote->id,
                    'invoice_number' => $creditNote->invoice_number,
                ])->values()
            ),
            // Why this invoice was credited, if it was. Set only by the
            // credit-note flow, never by the invoice form.
            'credit_reason' => $this->credit_reason,
            // How much of the invoice has been credited off it, as a positive
            // number of cents (credit notes store negative totals), and whether
            // that covers the whole document. Both are read off the same loaded
            // relation the banner uses, so they cost no extra query.
            'credited_total' => $this->when(
                $this->relationLoaded('creditNotes'),
                fn () => $this->creditedTotal()
            ),
            'credited_status' => $this->when(
                $this->relationLoaded('creditNotes'),
                function () {
                    $credited = $this->creditedTotal();

                    if ($credited === 0) {
                        return 'NONE';
                    }

                    return $credited === (int) $this->total ? 'FULL' : 'PARTIAL';
                }
            ),
            // Credited quantity per ORIGINAL line, which is what a partial
            // credit form needs to offer the remaining quantities. Emitted only
            // when the credit notes' items came along.
            'credited_quantities' => $this->when(
                $this->relationLoaded('creditNotes')
                    && $this->creditNotes->every(fn ($creditNote) => $creditNote->relationLoaded('items')),
                function () {
                    $quantities = [];

                    foreach ($this->creditNotes as $creditNote) {
                        foreach ($creditNote->items as $item) {
                            if (! $item->source_invoice_item_id) {
                                continue;
                            }

                            $quantities[$item->source_invoice_item_id] =
                                ($quantities[$item->source_invoice_item_id] ?? 0) + (float) $item->quantity;
                        }
                    }

                    // Cast to an object because the item ids are the keys: a
                    // nested array whose keys are all numeric is re-indexed to a
                    // list by the resource filter, which would throw the ids away.
                    return (object) $quantities;
                }
            ),
            'items' => $this->when($this->items()->exists(), function () {
                return InvoiceItemResource::collection($this->items);
            }),
            'customer' => $this->when($this->customer()->exists(), function () {
                return new CustomerResource($this->customer);
            }),
            'creator' => $this->when($this->creator()->exists(), function () {
                return new UserResource($this->creator);
            }),
            'taxes' => $this->when($this->taxes()->exists(), function () {
                return TaxResource::collection($this->taxes);
            }),
            'fields' => $this->when($this->fields()->exists(), function () {
                return CustomFieldValueResource::collection($this->fields);
            }),
            'company' => $this->when($this->company()->exists(), function () {
                return new CompanyResource($this->company);
            }),
            'currency' => $this->when($this->currency()->exists(), function () {
                return new CurrencyResource($this->currency);
            }),
        ];
    }

    /**
     * Sum of the loaded credit notes as a positive number of cents.
     */
    protected function creditedTotal(): int
    {
        return -(int) $this->creditNotes->sum('total');
    }
}
