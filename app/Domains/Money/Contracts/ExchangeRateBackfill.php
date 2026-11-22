<?php

namespace App\Domains\Money\Contracts;

interface ExchangeRateBackfill
{
    /**
     * The currencies of the company's documents that have no exchange rate yet.
     *
     * @return array<int, int>
     */
    public function currencyIdsMissingRates(int $companyId): array;

    /**
     * Give the company's documents that have no exchange rate yet the rate
     * entered for their currency, once. Other companies' documents, and
     * documents that already have a rate, are left alone.
     *
     * @param  array<int, array{id: int, exchange_rate: int|float|string}>  $currencies
     */
    public function apply(int $companyId, array $currencies): bool;
}
