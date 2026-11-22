<?php

namespace App\Domains\Taxation\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin payload for a tax type: the full definition, including how the tax is
 * calculated and which kind of row it is. The customer portal gets a narrower
 * view of the same record.
 */
class TaxTypeResource extends JsonResource
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
            'name' => $this->name,
            'percent' => $this->percent,
            'fixed_amount' => $this->fixed_amount,
            'calculation_type' => $this->calculation_type,
            'type' => $this->type,
            'transaction_type' => $this->transaction_type,
            'compound_tax' => $this->compound_tax,
            'collective_tax' => $this->collective_tax,
            'tax_category_code' => $this->tax_category_code,
            'tax_exemption_reason' => $this->tax_exemption_reason,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'company' => $this->when(
                $this->company()->exists(),
                fn () => new CompanyResource($this->company)
            ),
        ];
    }
}
