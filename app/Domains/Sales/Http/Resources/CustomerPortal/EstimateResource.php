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
 * An estimate as the customer portal publishes it.
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
class EstimateResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $estimate = $this->resource;

        return [
            'id' => $estimate->id,
            'estimate_date' => $estimate->estimate_date,
            'expiry_date' => $estimate->expiry_date,
            'estimate_number' => $estimate->estimate_number,
            'status' => $estimate->status,
            'reference_number' => $estimate->reference_number,
            'tax_per_item' => $estimate->tax_per_item,
            'discount_per_item' => $estimate->discount_per_item,
            'notes' => $estimate->notes,
            'discount' => $estimate->discount,
            'discount_type' => $estimate->discount_type,
            'discount_val' => $estimate->discount_val,
            'sub_total' => $estimate->sub_total,
            'total' => $estimate->total,
            'tax' => $estimate->tax,
            'unique_hash' => $estimate->unique_hash,
            'template_name' => $estimate->template_name,
            'customer_id' => $estimate->customer_id,
            'exchange_rate' => $estimate->exchange_rate,
            'base_discount_val' => $estimate->base_discount_val,
            'base_sub_total' => $estimate->base_sub_total,
            'base_total' => $estimate->base_total,
            'base_tax' => $estimate->base_tax,
            'currency_id' => $estimate->currency_id,
            'formatted_expiry_date' => $estimate->formattedExpiryDate,
            'formatted_estimate_date' => $estimate->formattedEstimateDate,
            'estimate_pdf_url' => $estimate->estimatePdfUrl,
            'items' => $this->when(
                $estimate->items()->exists(),
                fn () => EstimateItemResource::collection($estimate->items)
            ),
            'customer' => $this->when(
                $estimate->customer()->exists(),
                fn () => new CustomerResource($estimate->customer)
            ),
            'taxes' => $this->when(
                $estimate->taxes()->exists(),
                fn () => TaxResource::collection($estimate->taxes)
            ),
            'fields' => $this->when(
                $estimate->printedFields()->exists(),
                fn () => CustomFieldValueResource::collection($estimate->printedFields)
            ),
            'company' => $this->when(
                $estimate->company()->exists(),
                fn () => new CompanyResource($estimate->company)
            ),
            'currency' => $this->when(
                $estimate->currency()->exists(),
                fn () => new CurrencyResource($estimate->currency)
            ),
        ];
    }
}
