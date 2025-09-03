<?php

namespace App\Services\EInvoice\DataTransferObjects;

class SupplierData
{
    public function __construct(
        public string $name,
        public ?string $registrationName = null,
        public ?string $vatId = null,
        public ?string $taxId = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?AddressData $address = null,
        public ?BankData $bank = null,
    ) {}

    public static function fromCompany(\App\Models\Company $company): self
    {
        $address = null;
        if ($company->address) {
            $address = new AddressData(
                street: $company->address->address_street_1,
                city: $company->address->city,
                postalCode: $company->address->zip,
                country: $company->address->country_name,
                countryCode: $company->address->country_code,
                state: $company->address->state,
            );
        }

        return new self(
            name: $company->name,
            registrationName: $company->name,
            vatId: $company->vat_id,
            taxId: $company->tax_id,
            email: $company->email,
            phone: $company->phone,
            website: $company->website,
            address: $address,
        );
    }
}
