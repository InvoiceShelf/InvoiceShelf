<?php

namespace App\Http\Resources;

/**
 * API representation of a credit note (Stornorechnung).
 *
 * A credit note is an invoice with type = CREDIT_NOTE, so this mirrors
 * InvoiceResource and adds the credit-note specific fields: the type and a
 * compact reference to the original invoice it reverses. Using a dedicated
 * resource (rather than PaymentResource, which was the bug in PR #536) keeps
 * the credit-note payload explicit and self-describing.
 */
class CreditNoteResource extends InvoiceResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            // type + related_invoice_id come from InvoiceResource; this adds the
            // expanded reference to the original invoice being reversed.
            // Read off the loaded relation rather than probing it: the caller
            // eager-loads relatedInvoice, so an exists() query here would be
            // pure overhead.
            'related_invoice' => $this->when(
                $this->relationLoaded('relatedInvoice') && $this->relatedInvoice !== null,
                function () {
                    $related = $this->relatedInvoice;

                    return [
                        'id' => $related->id,
                        'invoice_number' => $related->invoice_number,
                        'invoice_date' => $related->invoice_date,
                        'formatted_invoice_date' => $related->formattedInvoiceDate,
                        'total' => $related->total,
                        'unique_hash' => $related->unique_hash,
                    ];
                }
            ),
        ]);
    }
}
