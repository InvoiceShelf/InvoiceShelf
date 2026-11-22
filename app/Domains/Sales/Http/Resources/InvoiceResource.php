<?php

namespace App\Domains\Sales\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Accounts\Http\Resources\UserResource;
use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Taxation\Http\Resources\TaxResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * An invoice as the admin API publishes it.
 *
 * The stored columns come first, then the derived reading aids: dates rendered
 * in the company's date format, the shareable PDF link, whether the document is
 * still editable and whether the payment module is switched on.
 *
 * Two different gating strategies live side by side here, and the difference is
 * intentional.
 *
 * The crediting and settlement blocks -- `credit_notes`, `credited_total`,
 * `credited_status`, `credited_quantities` and `payment_allocations` -- are
 * published only when the caller already eager-loaded the relation they read.
 * Probing those per row would cost extra queries on every line of a paginated
 * listing, so an index response simply omits them and the detail response,
 * which loads `creditNotes.items` and `allocations.payment`, carries them.
 *
 * The trailing associations -- lines, contact, author, taxes, custom field
 * values, company, currency -- instead probe the relation with an existence
 * query each time, so they are correct whether or not anything was eager
 * loaded. That is a query per association per row; it is the established shape
 * of this payload and is preserved deliberately.
 */
class InvoiceResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $invoice = $this->resource;
        $creditNotesLoaded = $invoice->relationLoaded('creditNotes');

        return [
            'id' => $invoice->id,
            'invoice_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'invoice_number' => $invoice->invoice_number,
            'reference_number' => $invoice->reference_number,
            'type' => $invoice->type,
            'related_invoice_id' => $invoice->related_invoice_id,
            'status' => $invoice->status,
            'paid_status' => $invoice->paid_status,
            'tax_per_item' => $invoice->tax_per_item,
            'tax_included' => $invoice->tax_included,
            'discount_per_item' => $invoice->discount_per_item,
            'notes' => $invoice->notes,
            'discount_type' => $invoice->discount_type,
            'discount' => $invoice->discount,
            'discount_val' => $invoice->discount_val,
            'sub_total' => $invoice->sub_total,
            'total' => $invoice->total,
            'tax' => $invoice->tax,
            'due_amount' => $invoice->due_amount,
            'sent' => $invoice->sent,
            'viewed' => $invoice->viewed,
            'unique_hash' => $invoice->unique_hash,
            'template_name' => $invoice->template_name,
            'customer_id' => $invoice->customer_id,
            'recurring_invoice_id' => $invoice->recurring_invoice_id,
            'sequence_number' => $invoice->sequence_number,
            'exchange_rate' => $invoice->exchange_rate,
            'base_discount_val' => $invoice->base_discount_val,
            'base_sub_total' => $invoice->base_sub_total,
            'base_total' => $invoice->base_total,
            'creator_id' => $invoice->creator_id,
            'base_tax' => $invoice->base_tax,
            'base_due_amount' => $invoice->base_due_amount,
            'currency_id' => $invoice->currency_id,
            'formatted_created_at' => $invoice->formattedCreatedAt,
            'invoice_pdf_url' => $invoice->invoicePdfUrl,
            'formatted_invoice_date' => $invoice->formattedInvoiceDate,
            'formatted_due_date' => $invoice->formattedDueDate,
            'allow_edit' => $invoice->allow_edit,
            'payment_module_enabled' => $invoice->payment_module_enabled,
            'payment_link' => $invoice->payment_link,
            'sales_tax_type' => $invoice->sales_tax_type,
            'sales_tax_address_type' => $invoice->sales_tax_address_type,
            'overdue' => $invoice->overdue,

            // Just enough of each reversing document for the UI to flag the
            // invoice as cancelled and link through to the storno. Suppressed
            // when there are none, so the key's presence is itself the signal.
            'credit_notes' => $this->when(
                $creditNotesLoaded && $invoice->creditNotes->isNotEmpty(),
                fn () => $this->creditNoteReferences()
            ),

            // Written by the crediting flow only; the invoice form never sets it.
            'credit_reason' => $invoice->credit_reason,

            // How much has been credited off this invoice and whether that
            // covers the document in full. Both read the same already-loaded
            // relation the banner above uses, so neither costs a query.
            'credited_total' => $this->when(
                $creditNotesLoaded,
                fn () => $this->creditedTotal()
            ),
            'credited_status' => $this->when(
                $creditNotesLoaded,
                fn () => $this->creditedStatus()
            ),

            // Credited quantity per line of THIS invoice, which is what a
            // partial-credit form needs in order to offer what is left. Needs
            // the reversing documents' own lines, so it waits for those too.
            'credited_quantities' => $this->when(
                $creditNotesLoaded
                    && $invoice->creditNotes->every(fn ($note) => $note->relationLoaded('items')),
                fn () => $this->creditedQuantities()
            ),

            // Settlement is reported through the allocation rows rather than a
            // payment relation on the invoice itself. Loaded for the detail
            // response only, so listings stay free of per-row payment queries.
            'payment_allocations' => $this->when(
                $invoice->relationLoaded('allocations'),
                fn () => $this->allocationSummaries()
            ),

            'items' => $this->when(
                $invoice->items()->exists(),
                fn () => InvoiceItemResource::collection($invoice->items)
            ),
            'customer' => $this->when(
                $invoice->customer()->exists(),
                fn () => new CustomerResource($invoice->customer)
            ),
            'creator' => $this->when(
                $invoice->creator()->exists(),
                fn () => new UserResource($invoice->creator)
            ),
            'taxes' => $this->when(
                $invoice->taxes()->exists(),
                fn () => TaxResource::collection($invoice->taxes)
            ),
            'fields' => $this->when(
                $invoice->fields()->exists(),
                fn () => CustomFieldValueResource::collection($invoice->fields)
            ),
            'company' => $this->when(
                $invoice->company()->exists(),
                fn () => new CompanyResource($invoice->company)
            ),
            'currency' => $this->when(
                $invoice->currency()->exists(),
                fn () => new CurrencyResource($invoice->currency)
            ),
        ];
    }

    /**
     * Everything credited off this invoice, in cents, as a positive number.
     *
     * Credit notes store their amounts negated, so the loaded relation's sum is
     * flipped back on the way out.
     */
    protected function creditedTotal(): int
    {
        return -(int) $this->creditNotes->sum('total');
    }

    /**
     * Identifier and number of each document reversing this invoice.
     *
     * Reindexed, because the loaded relation's keys are positions in the parent
     * result set and would otherwise be published as object keys.
     */
    private function creditNoteReferences(): Collection
    {
        return $this->creditNotes
            ->map(fn ($note) => [
                'id' => $note->id,
                'invoice_number' => $note->invoice_number,
            ])
            ->values();
    }

    /**
     * How far the crediting has gone: none of it, all of it, or part of it.
     */
    private function creditedStatus(): string
    {
        $credited = $this->creditedTotal();

        return match (true) {
            $credited === 0 => 'NONE',
            $credited === (int) $this->total => 'FULL',
            default => 'PARTIAL',
        };
    }

    /**
     * Credited quantity per line of this invoice, keyed by the line's id.
     *
     * Reversing lines that do not point back at an original line contribute
     * nothing. The result is handed over as an object rather than an array: the
     * keys are line ids, and an all-numeric nested array would be reindexed
     * into a list by the resource filter, throwing those ids away.
     */
    private function creditedQuantities(): object
    {
        $quantities = [];

        foreach ($this->creditNotes as $note) {
            foreach ($note->items as $line) {
                $source = $line->source_invoice_item_id;

                if (! $source) {
                    continue;
                }

                $quantities[$source] = ($quantities[$source] ?? 0) + (float) $line->quantity;
            }
        }

        return (object) $quantities;
    }

    /**
     * One row per payment allocated against this invoice.
     *
     * The paying document is nested only when it came along with the
     * allocation; otherwise the row still reports the allocated amounts and
     * leaves the payment null rather than fetching it.
     */
    private function allocationSummaries(): Collection
    {
        return $this->allocations
            ->map(fn ($allocation) => [
                'id' => $allocation->id,
                'payment_id' => $allocation->payment_id,
                'amount' => $allocation->amount,
                'base_amount' => $allocation->base_amount,
                'payment' => $this->allocatedPayment($allocation),
            ])
            ->values();
    }

    /**
     * The paying document behind one allocation, when it is already loaded.
     */
    private function allocatedPayment($allocation): ?array
    {
        if (! $allocation->relationLoaded('payment') || ! $allocation->payment) {
            return null;
        }

        return [
            'id' => $allocation->payment->id,
            'payment_number' => $allocation->payment->payment_number,
            'formatted_payment_date' => $allocation->payment->formattedPaymentDate,
        ];
    }
}
