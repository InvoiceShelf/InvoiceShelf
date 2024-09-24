<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
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
            'code' => $this->code,
            'symbol' => $this->symbol,
            'precision' => $this->precision,
            'thousand_separator' => $this->thousand_separator,
            'decimal_separator' => $this->decimal_separator,
            'swap_currency_symbol' => $this->swap_currency_symbol,
            'exchange_rate' => $this->exchange_rate,
        ];
    }
}
