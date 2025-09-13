<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaxTypeResource extends JsonResource
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
            'name' => $this->name,
            'percent' => $this->percent,
            'fixed_amount' => $this->fixed_amount,
            'calculation_type' => $this->calculation_type,
            'type' => $this->type,
            'compound_tax' => $this->compound_tax,
            'collective_tax' => $this->collective_tax,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'company' => $this->when($this->company()->exists(), function () {
                return new CompanyResource($this->company);
            }),
        ];
    }
}
