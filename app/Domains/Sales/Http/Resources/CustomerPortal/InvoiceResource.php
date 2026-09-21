<?php

namespace App\Domains\Sales\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\CompanyResource;
use App\Domains\Contacts\Http\Resources\CustomerPortal\CustomerResource;
use App\Domains\Metadata\Http\Resources\CustomerPortal\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CustomerPortal\CurrencyResource;
use App\Domains\Taxation\Http\Resources\CustomerPortal\TaxResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice as the customer portal publishes it.
 *
 * A narrower view than the admin one. Nothing about the internal handling of
 * the document travels: no document type or credit-note back-links, no author,
 * no editability flag, no crediting or allocation detail, and no sales-tax
 * configuration. What is left is what the customer's own copy of the invoice
 * shows -- the figures, the dates in the company's format, the shareable PDF
 * link and whether the document is overdue.
 *
 * The notes are published twice, in two different renderings: `notes` carries
 * the placeholders already interpolated, `formatted_notes` the model's own
 * formatting of the stored value. Both keys are consumed by the portal, so both
 * stay. Each related record is gated behind an existence probe on its relation.
 */
class InvoiceResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'invoice_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'invoice_number' => $invoice->invoice_number,
            'reference_number' => $invoice->reference_number,
            'status' => $invoice->status,
            'paid_status' => $invoice->paid_status,
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'notes' => $invoice->getNotes(),
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
            'base_discount_val' => $invoice->base_discount_val,
            'base_sub_total' => $invoice->base_sub_total,
            'base_total' => $invoice->base_total,
            'base_tax' => $invoice->base_tax,
            'base_due_amount' => $invoice->base_due_amount,
            'currency_id' => $invoice->currency_id,
            'formatted_created_at' => $invoice->formattedCreatedAt,
            'formatted_notes' => $invoice->formattedNotes,
            'invoice_pdf_url' => $invoice->invoicePdfUrl,
            'formatted_invoice_date' => $invoice->formattedInvoiceDate,
            'formatted_due_date' => $invoice->formattedDueDate,
            'payment_module_enabled' => $invoice->payment_module_enabled,
            'overdue' => $invoice->overdue,
            'items' => $this->when(
                $invoice->items()->exists(),
                fn () => InvoiceItemResource::collection($invoice->items)
            ),
            'customer' => $this->when(
                $invoice->customer()->exists(),
                fn () => new CustomerResource($invoice->customer)
            ),
            'taxes' => $this->when(
                $invoice->taxes()->exists(),
                fn () => TaxResource::collection($invoice->taxes)
            ),
            'fields' => $this->when(
                $invoice->printedFields()->exists(),
                fn () => CustomFieldValueResource::collection($invoice->printedFields)
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
}
