<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class CurrencyService
{
    /**
     * Retrieve all currencies with commonly-used currencies sorted first.
     *
     * @return Collection<int, Currency>
     */
    public function getAllWithCommonFirst(): Collection
    {
        $currencies = Currency::query()->get();

        $commonCodes = Currency::COMMON_CURRENCY_CODES;

        $common = $currencies->filter(fn (Currency $c): bool => in_array($c->code, $commonCodes, true))
            ->sortBy(fn (Currency $c): int => array_search($c->code, $commonCodes));

        $rest = $currencies->reject(fn (Currency $c): bool => in_array($c->code, $commonCodes, true))
            ->sortBy('name');

        return $common->concat($rest)->values();
    }
}
