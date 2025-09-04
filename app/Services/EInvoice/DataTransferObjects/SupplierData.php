<?php

namespace App\Services\EInvoice\DataTransferObjects;

class SupplierData
{
    public function __construct(
        public string $name,
        public ?string $registrationName = null,
        public ?string $taxId = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?AddressData $address = null,
        public ?BankData $bank = null,
        public ?ElectronicAddressData $electronicAddress = null,
        public ?CompanyIdData $companyId = null,
    ) {}

    public static function fromCompany(\App\Models\Company $company): self
    {
        // Ensure country relation is loaded
        if (! $company->relationLoaded('address.country')) {
            $company->load('address.country');
        }

        $address = null;
        if ($company->address) {
            $address = new AddressData(
                street: $company->address->address_street_1,
                city: $company->address->city,
                postalCode: $company->address->zip,
                country: $company->address->country_name,
                countryCode: $company->address->country ? $company->address->country->code : null,
                state: $company->address->state,
            );
        }

        // Create electronic address if email is available
        $electronicAddress = null;
        if ($company->email) {
            $electronicAddress = new ElectronicAddressData(
                value: $company->email,
                schemeId: 'EM', // Email scheme
                schemeName: 'Electronic Mail'
            );
        }

        // Create company ID
        $companyId = null;
        if ($company->tax_id) {
            $companyId = new CompanyIdData(
                value: $company->tax_id,
                schemeId: '0183', // VAT scheme
                schemeName: 'VAT'
            );
        }

        return new self(
            name: $company->name,
            registrationName: $company->name,
            taxId: $company->tax_id,
            email: $company->email,
            phone: $company->phone,
            website: $company->website,
            address: $address,
            electronicAddress: $electronicAddress,
            companyId: $companyId,
        );
    }
}
