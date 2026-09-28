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

/**
 * A recurring invoice as the admin API publishes it.
 *
 * A recurring invoice is a schedule plus a template of a document, so the
 * payload opens with the scheduling half -- when it starts, when it fires next,
 * the cron frequency, how it is limited and whether generated invoices go out
 * on their own, each key date also rendered in the company's date format -- and
 * continues with the template half, the same amounts and flags an invoice
 * carries.
 *
 * The related records are each gated behind an existence probe on the relation,
 * including `invoices`, which publishes the documents this schedule has already
 * produced through the invoice resource itself.
 */
class RecurringInvoiceResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $recurring = $this->resource;

        return [
            'id' => $recurring->id,
            'starts_at' => $recurring->starts_at,
            'formatted_starts_at' => $recurring->formattedStartsAt,
            'formatted_created_at' => $recurring->formattedCreatedAt,
            'formatted_next_invoice_at' => $recurring->formattedNextInvoiceAt,
            'formatted_limit_date' => $recurring->formattedLimitDate,
            'send_automatically' => $recurring->send_automatically,
            'notify_creator' => (bool) $recurring->notify_creator,
            'last_error' => $recurring->last_error,
            'customer_id' => $recurring->customer_id,
            'company_id' => $recurring->company_id,
            'creator_id' => $recurring->creator_id,
            'status' => $recurring->status,
            'next_invoice_at' => $recurring->next_invoice_at,
            'frequency' => $recurring->frequency,
            'limit_by' => $recurring->limit_by,
            'limit_count' => $recurring->limit_count,
            'limit_date' => $recurring->limit_date,
            'exchange_rate' => $recurring->exchange_rate,
            'tax_per_item' => $recurring->tax_per_item,
            'tax_included' => $recurring->tax_included,
            'discount_per_item' => $recurring->discount_per_item,
            'notes' => $recurring->notes,
            'discount_type' => $recurring->discount_type,
            'discount' => $recurring->discount,
            'discount_val' => $recurring->discount_val,
            'sub_total' => $recurring->sub_total,
            'total' => $recurring->total,
            'tax' => $recurring->tax,
            'due_amount' => $recurring->due_amount,
            'template_name' => $recurring->template_name,
            'sales_tax_type' => $recurring->sales_tax_type,
            'sales_tax_address_type' => $recurring->sales_tax_address_type,
            'fields' => $this->when(
                $recurring->fields()->exists(),
                fn () => CustomFieldValueResource::collection($recurring->fields)
            ),
            'items' => $this->when(
                $recurring->items()->exists(),
                fn () => InvoiceItemResource::collection($recurring->items)
            ),
            'customer' => $this->when(
                $recurring->customer()->exists(),
                fn () => new CustomerResource($recurring->customer)
            ),
            'company' => $this->when(
                $recurring->company()->exists(),
                fn () => new CompanyResource($recurring->company)
            ),
            'invoices' => $this->when(
                $recurring->invoices()->exists(),
                fn () => InvoiceResource::collection($recurring->invoices)
            ),
            'taxes' => $this->when(
                $recurring->taxes()->exists(),
                fn () => TaxResource::collection($recurring->taxes)
            ),
            'creator' => $this->when(
                $recurring->creator()->exists(),
                fn () => new UserResource($recurring->creator)
            ),
            'currency' => $this->when(
                $recurring->currency()->exists(),
                fn () => new CurrencyResource($recurring->currency)
            ),
        ];
    }
}
