<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class EstimateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'estimate_date' => $this->estimate_date,
            'expiry_date' => $this->expiry_date,
            'estimate_number' => $this->estimate_number,
            'status' => $this->status,
            'reference_number' => $this->reference_number,
            'tax_per_item' => $this->tax_per_item,
            'discount_per_item' => $this->discount_per_item,
            'notes' => $this->notes,
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'discount_val' => $this->discount_val,
            'sub_total' => $this->sub_total,
            'total' => $this->total,
            'tax' => $this->tax,
            'unique_hash' => $this->unique_hash,
            'template_name' => $this->template_name,
            'customer_id' => $this->customer_id,
            'exchange_rate' => $this->exchange_rate,
            'base_discount_val' => $this->base_discount_val,
            'base_sub_total' => $this->base_sub_total,
            'base_total' => $this->base_total,
            'base_tax' => $this->base_tax,
            'currency_id' => $this->currency_id,
            'formatted_expiry_date' => $this->formattedExpiryDate,
            'formatted_estimate_date' => $this->formattedEstimateDate,
            'estimate_pdf_url' => $this->estimatePdfUrl,
            'items' => $this->when($this->items()->exists(), function () {
                return EstimateItemResource::collection($this->items);
            }),
            'customer' => $this->when($this->customer()->exists(), function () {
                return new CustomerResource($this->customer);
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
}
