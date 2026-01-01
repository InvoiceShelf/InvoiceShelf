<?php

namespace App\Services\EInvoice\DataTransferObjects;

class BankData
{
    public function __construct(
        public ?string $iban = null,
        public ?string $bic = null,
        public ?string $bankName = null,
        public ?string $accountNumber = null,
    ) {}
}
