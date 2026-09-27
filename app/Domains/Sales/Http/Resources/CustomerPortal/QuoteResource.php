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
 * A quote as the customer portal publishes it.
 *
 * A narrower view than the admin one: no author, no sequence number, no
 * tax-inclusive flag and no sales-tax configuration -- only what the customer's
 * own copy of the offer shows, including both expiry and issue dates in the
 * company's format and the shareable PDF link.
 *
 * The notes are the raw stored value here, not the interpolated rendering the
 * admin payload publishes. Each related record is gated behind an existence
 * probe on its relation, and every nested resource is the portal variant.
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
            'discount_per_item' => $quote->discount_per_item,
            'notes' => $quote->notes,
            'discount' => $quote->discount,
            'discount_type' => $quote->discount_type,
            'discount_val' => $quote->discount_val,
            'sub_total' => $quote->sub_total,
            'total' => $quote->total,
            'tax' => $quote->tax,
            'unique_hash' => $quote->unique_hash,
            'template_name' => $quote->template_name,
            'customer_id' => $quote->customer_id,
            'exchange_rate' => $quote->exchange_rate,
            'base_discount_val' => $quote->base_discount_val,
            'base_sub_total' => $quote->base_sub_total,
            'base_total' => $quote->base_total,
            'base_tax' => $quote->base_tax,
            'currency_id' => $quote->currency_id,
            'formatted_expiry_date' => $quote->formattedExpiryDate,
            'formatted_quote_date' => $quote->formattedQuoteDate,
            'quote_pdf_url' => $quote->quotePdfUrl,
            'items' => $this->when(
                $quote->items()->exists(),
                fn () => QuoteItemResource::collection($quote->items)
            ),
            'customer' => $this->when(
                $quote->customer()->exists(),
                fn () => new CustomerResource($quote->customer)
            ),
            'taxes' => $this->when(
                $quote->taxes()->exists(),
                fn () => TaxResource::collection($quote->taxes)
            ),
            'fields' => $this->when(
                $quote->printedFields()->exists(),
                fn () => CustomFieldValueResource::collection($quote->printedFields)
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
