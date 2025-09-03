<?php

namespace App\Services\EInvoice\DataTransferObjects;

class CustomerData
{
    public function __construct(
        public string $name,
        public ?string $registrationName = null,
        public ?string $vatId = null,
        public ?string $taxId = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?AddressData $billingAddress = null,
        public ?AddressData $shippingAddress = null,
    ) {}

    public static function fromCustomer(\App\Models\Customer $customer): self
    {
        $billingAddress = null;
        if ($customer->billingAddress) {
            $billingAddress = new AddressData(
                street: $customer->billingAddress->address_street_1,
                city: $customer->billingAddress->city,
                postalCode: $customer->billingAddress->zip,
                country: $customer->billingAddress->country_name,
                countryCode: $customer->billingAddress->country_code,
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
                countryCode: $customer->shippingAddress->country_code,
                state: $customer->shippingAddress->state,
            );
        }

        return new self(
            name: $customer->name,
            registrationName: $customer->name,
            vatId: $customer->vat_id,
            taxId: $customer->tax_id,
            email: $customer->email,
            phone: $customer->phone,
            website: $customer->website,
            billingAddress: $billingAddress,
            shippingAddress: $shippingAddress,
        );
    }
}
