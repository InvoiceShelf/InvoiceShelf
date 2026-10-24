<?php

namespace App\Domains\Catalog\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The payload for a measurement unit: its identity plus the company it
 * belongs to.
 *
 * The owning company is expanded only when the relation resolves, so the
 * `company` key is absent -- not null -- for a unit whose company row is
 * missing. Checking that costs an existence query per serialised unit.
 */
class UnitResource extends JsonResource
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
            'unit_code' => $this->unit_code,
            'company_id' => $this->company_id,
            'company' => $this->when(
                $this->company()->exists(),
                fn () => new CompanyResource($this->company)
            ),
        ];
    }
}
