<?php

namespace App\Platform\Mcp\Tools\Contacts\Concerns;

use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Country;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;

/**
 * A customer's arguments, and the customer form's submission built from them
 * on top of what is stored, since the form always sends the whole record.
 */
trait DescribesCustomers
{
    private const FIELDS = ['name', 'email', 'phone', 'company_name', 'contact_name', 'website', 'tax_id'];

    private const ADDRESS_FIELDS = ['name' => 'name', 'street_1' => 'address_street_1', 'street_2' => 'address_street_2', 'city' => 'city', 'state' => 'state', 'zip' => 'zip', 'phone' => 'phone'];

    /**
     * @return array<string, mixed>
     */
    protected function customerSchema(JsonSchema $schema, bool $creating): array
    {
        $address = fn (string $which) => $schema->object([
            'name' => $schema->string(),
            'street_1' => $schema->string(),
            'street_2' => $schema->string(),
            'city' => $schema->string(),
            'state' => $schema->string(),
            'zip' => $schema->string(),
            'country' => $schema->string()->description('Country code like "DE", or its English name.'),
            'phone' => $schema->string(),
        ])->description("The {$which} address; given fields replace the stored ones.");

        $name = $schema->string()->description('The name shown on documents.');

        return [
            'name' => $creating ? $name->required() : $name,
            'email' => $schema->string()->format('email'),
            'phone' => $schema->string(),
            'company_name' => $schema->string(),
            'contact_name' => $schema->string(),
            'website' => $schema->string(),
            'tax_id' => $schema->string(),
            'currency' => $schema->string()->description('ISO code of the currency they are billed in, like "EUR"; the company currency when left out. It cannot change once they have documents.'),
            'billing_address' => $address('billing'),
            'shipping_address' => $address('shipping'),
            'custom_fields' => $schema->array()->items($schema->object([
                'slug' => $schema->string(),
                'value' => $schema->string(),
            ]))->description('Answers to the custom fields that apply to customers.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $given
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function customerInput(array $given, int|string|null $homeCurrency, ?Customer $existing = null): array
    {
        $input = [];

        foreach (self::FIELDS as $field) {
            $input[$field] = array_key_exists($field, $given) ? $given[$field] : $existing?->{$field};
        }

        $input['enable_portal'] = (bool) ($existing?->enable_portal ?? false);
        $input['currency_id'] = $existing?->currency_id ?? $homeCurrency;

        if (isset($given['currency'])) {
            $currency = Currency::query()->where('code', strtoupper((string) $given['currency']))->value('id');

            if (! $currency) {
                throw ValidationException::withMessages(['currency' => 'There is no currency with the code '.$given['currency'].'.']);
            }

            $input['currency_id'] = $currency;
        }

        foreach (['billing' => 'billingAddress', 'shipping' => 'shippingAddress'] as $block => $relation) {
            $input[$block] = $this->addressInput($given["{$block}_address"] ?? null, $existing?->{$relation}, $block);
        }

        if (array_key_exists('custom_fields', $given)) {
            $input['customFields'] = array_map(
                fn ($answer) => is_array($answer) ? array_intersect_key($answer, array_flip(['slug', 'id', 'value'])) : $answer,
                (array) $given['custom_fields'],
            );
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    private function addressInput(mixed $given, ?Address $stored, string $block): array
    {
        $address = [];

        foreach (self::ADDRESS_FIELDS as $argument => $column) {
            $address[$column] = is_array($given) && array_key_exists($argument, $given) ? $given[$argument] : $stored?->{$column};
        }

        $address['country_id'] = $stored?->country_id;

        if (is_array($given) && ! empty($given['country'])) {
            $country = Country::query()
                ->where('code', strtoupper((string) $given['country']))
                ->orWhere('name', $given['country'])
                ->value('id');

            if (! $country) {
                throw ValidationException::withMessages(["{$block}_address.country" => 'There is no country '.$given['country'].'.']);
            }

            $address['country_id'] = $country;
        }

        return $address;
    }
}
