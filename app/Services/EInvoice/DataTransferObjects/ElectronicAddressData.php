<?php

namespace App\Services\EInvoice\DataTransferObjects;

class ElectronicAddressData
{
    public function __construct(
        public string $value,
        public string $schemeId,
        public ?string $schemeName = null,
    ) {}
}
