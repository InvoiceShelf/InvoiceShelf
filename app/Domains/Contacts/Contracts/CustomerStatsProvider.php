<?php

namespace App\Domains\Contacts\Contracts;

use App\Domains\Contacts\Models\Customer;

interface CustomerStatsProvider
{
    /**
     * One customer's money over the company's fiscal year, the one before it,
     * or the 'Y-m-d' range from $fromDate to $toDate when both are given.
     *
     * @return array<string, mixed>
     */
    public function get(
        Customer $customer,
        int $companyId,
        bool $previousYear = false,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array;
}
