<?php

namespace App\Services\EInvoice\DataTransferObjects;

class CustomerData
{
    public function __construct(
        public string $name,
        public ?string $registrationName = null,
        public ?string $taxId = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?AddressData $billingAddress = null,
        public ?AddressData $shippingAddress = null,
        public ?ElectronicAddressData $electronicAddress = null,
        public ?CompanyIdData $companyId = null,
    ) {}

    public static function fromCustomer(\App\Models\Customer $customer): self
    {
        // Ensure country relations are loaded
        if (! $customer->relationLoaded('billingAddress.country')) {
            $customer->load('billingAddress.country');
        }
        if (! $customer->relationLoaded('shippingAddress.country')) {
            $customer->load('shippingAddress.country');
        }

        $billingAddress = null;
        if ($customer->billingAddress) {
            $billingAddress = new AddressData(
                street: $customer->billingAddress->address_street_1,
                city: $customer->billingAddress->city,
                postalCode: $customer->billingAddress->zip,
                country: $customer->billingAddress->country_name,
                countryCode: $customer->billingAddress->country ? $customer->billingAddress->country->code : null,
                state: $customer->billingAddress->state,
            );
        }

        $shippingAddress = null;
        if ($customer->shippingAddress) {
            $shippingAddress = new AddressData(
                street: $customer->shippingAddress->address_street_1,
                city: $customer->shippingAddress->city,
                postalCode: $customer->shippingAddress->zip,
                country: $customer->shippingAddress->country_name,
                countryCode: $customer->shippingAddress->country ? $customer->shippingAddress->country->code : null,
                state: $customer->shippingAddress->state,
            );
        }

        // Create electronic address if email is available
        $electronicAddress = null;
        if ($customer->email) {
            $electronicAddress = new ElectronicAddressData(
                value: $customer->email,
                schemeId: 'EM', // Email scheme
                schemeName: 'Electronic Mail'
            );
        }

        // Create company ID
        $companyId = null;
        if ($customer->tax_id) {
            $companyId = new CompanyIdData(
                value: $customer->tax_id,
                schemeId: '0183', // VAT scheme
                schemeName: 'VAT'
            );
        }

        return new self(
            name: $customer->name,
            registrationName: $customer->name,
            taxId: $customer->tax_id,
            email: $customer->email,
            phone: $customer->phone,
            website: $customer->website,
            billingAddress: $billingAddress,
            shippingAddress: $shippingAddress,
            electronicAddress: $electronicAddress,
            companyId: $companyId,
        );
    }
}
