<?php

namespace App\Services\EInvoice\DataTransferObjects;

class AddressData
{
    public function __construct(
        public ?string $street = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public ?string $countryCode = null,
        public ?string $state = null,
        public ?string $additionalStreet = null,
    ) {}
}
