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
 * A quote as the admin API publishes it.
 *
 * Carries the stored columns, the dates already rendered in the company's date
 * format, the shareable PDF link, and the notes with their placeholders
 * interpolated rather than the raw template stored on the row.
 *
 * The related records -- lines, contact, author, document taxes, custom field
 * values, company and currency -- are each gated behind an existence probe on
 * the relation, so a missing one leaves its key out of the payload entirely.
 * Every probe is its own query, which is deliberate here: the quote payload
 * has to answer correctly whether or not the caller eager-loaded anything.
 */
class QuoteResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $quote = $this->resource;

        return [
            'id' => $quote->id,
            'quote_date' => $quote->quote_date,
            'expiry_date' => $quote->expiry_date,
            'quote_number' => $quote->quote_number,
            'status' => $quote->status,
            'reference_number' => $quote->reference_number,
            'tax_per_item' => $quote->tax_per_item,
            'tax_included' => $quote->tax_included,
            'discount_per_item' => $quote->discount_per_item,
            'notes' => $quote->getNotes(),
            'discount' => $quote->discount,
            'discount_type' => $quote->discount_type,
            'discount_val' => $quote->discount_val,
            'sub_total' => $quote->sub_total,
            'total' => $quote->total,
            'tax' => $quote->tax,
            'unique_hash' => $quote->unique_hash,
            'creator_id' => $quote->creator_id,
            'template_name' => $quote->template_name,
            'customer_id' => $quote->customer_id,
            'exchange_rate' => $quote->exchange_rate,
            'base_discount_val' => $quote->base_discount_val,
            'base_sub_total' => $quote->base_sub_total,
            'base_total' => $quote->base_total,
            'base_tax' => $quote->base_tax,
            'sequence_number' => $quote->sequence_number,
            'currency_id' => $quote->currency_id,
            'formatted_expiry_date' => $quote->formattedExpiryDate,
            'formatted_quote_date' => $quote->formattedQuoteDate,
            'quote_pdf_url' => $quote->quotePdfUrl,
            'sales_tax_type' => $quote->sales_tax_type,
            'sales_tax_address_type' => $quote->sales_tax_address_type,
            'items' => $this->when(
                $quote->items()->exists(),
                fn () => QuoteItemResource::collection($quote->items)
            ),
            'customer' => $this->when(
                $quote->customer()->exists(),
                fn () => new CustomerResource($quote->customer)
            ),
            'creator' => $this->when(
                $quote->creator()->exists(),
                fn () => new UserResource($quote->creator)
            ),
            'taxes' => $this->when(
                $quote->taxes()->exists(),
                fn () => TaxResource::collection($quote->taxes)
            ),
            'fields' => $this->when(
                $quote->fields()->exists(),
                fn () => CustomFieldValueResource::collection($quote->fields)
            ),
            'company' => $this->when(
                $quote->company()->exists(),
                fn () => new CompanyResource($quote->company)
            ),
            'currency' => $this->when(
                $quote->currency()->exists(),
                fn () => new CurrencyResource($quote->currency)
            ),
        ];
    }
}
