<?php

namespace App\Services\EInvoice\DataTransferObjects;

class TaxData
{
    public function __construct(
        public string $type,
        public float $rate,
        public float $amount,
        public float $baseAmount,
        public ?string $category = null,
        public ?string $exemptionReason = null,
    ) {}
}
