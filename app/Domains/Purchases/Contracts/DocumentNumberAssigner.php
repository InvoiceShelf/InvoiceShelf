<?php

namespace App\Domains\Purchases\Contracts;

use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierPayment;
use App\Domains\Purchases\Models\SupplierRefund;

/**
 * Numbers purchasing documents from the company's number format, one sequence
 * per document kind and company, the way sales documents are numbered.
 */
interface DocumentNumberAssigner
{
    /**
     * The number and sequence the next document of this kind should carry.
     *
     * @param  class-string<Bill|SupplierCredit|SupplierPayment|SupplierRefund>  $model
     * @return array{number: string, sequence_number: int}
     */
    public function next(string $model, int $companyId): array;
}
