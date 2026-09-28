<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Money\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency_id' => (int) $this['currency_id'],
            'currency' => new CurrencyResource($this['currency']),
            'due' => (int) $this['due'],
            'advances' => (int) $this['advances'],
            'credits' => (int) $this['credits'],
        ];
    }
}
