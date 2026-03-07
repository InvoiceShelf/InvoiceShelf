<?php

namespace App\Services\EInvoice\DataTransferObjects;

class LineItemData
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description = null,
        public float $quantity = 1.0,
        public float $unitPrice = 0.0,
        public float $netAmount = 0.0,
        public float $taxRate = 0.0,
        public float $taxAmount = 0.0,
        public string $unit = 'EA',
        public ?string $itemClassification = null,
        public ?string $itemClassificationCode = null,
        public ?string $taxCategory = null,
    ) {}
}
