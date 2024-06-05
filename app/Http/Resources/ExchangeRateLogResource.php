<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateLogResource extends JsonResource
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
            'company_id' => $this->company_id,
            'base_currency_id' => $this->base_currency_id,
            'currency_id' => $this->currency_id,
            'exchange_rate' => $this->exchange_rate,
        ];
    }
}
