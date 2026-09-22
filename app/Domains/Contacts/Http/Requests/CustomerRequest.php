<?php

namespace App\Domains\Contacts\Http\Requests;

use App\Domains\Contacts\Models\Address;
use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use App\Rules\IdnEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Validates and reshapes the admin contact form, for both create and update.
 *
 * Only the display name is actually required and only the email is actually
 * checked, so most of the work here is deciding what counts as "an address was
 * submitted" and handing the service three tidy arrays.
 */
class CustomerRequest extends FormRequest
{
    use ValidatesCustomFields;

    /**
     * Columns copied straight from the validated payload onto the contact row.
     *
     * The three document prefixes at the end carry no validation rule, so
     * `validated()` never holds them and they can never be written through
     * here. Left listed all the same.
     */
    private const PERSISTED_FIELDS = [
        'name',
        'email',
        'currency_id',
        'password',
        'phone',
        'prefix',
        'tax_id',
        'company_name',
        'contact_name',
        'website',
        'enable_portal',
        'estimate_prefix',
        'payment_prefix',
        'invoice_prefix',
    ];

    /** Optional and unchecked — declared only so the payload is not rejected. */
    private const OPTIONAL_FIELDS = [
        'password',
        'phone',
        'company_name',
        'contact_name',
        'website',
        'prefix',
        'tax_id',
    ];

    /** The shape of one address block, billing and shipping alike. */
    private const ADDRESS_FIELDS = [
        'name',
        'address_street_1',
        'address_street_2',
        'city',
        'state',
        'country_id',
        'zip',
        'phone',
        'fax',
    ];

    /**
     * Nothing is settled at this layer; the contact policy runs in the
     * controller, so every caller that got this far is let through.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Built in the order the fields are declared, so the error bag comes back
     * in the same order it always has.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required'],
            'email' => $this->emailRules(),
        ];

        foreach (self::OPTIONAL_FIELDS as $field) {
            $rules[$field] = ['nullable'];
        }

        $rules['enable_portal'] = ['boolean'];
        $rules['currency_id'] = ['nullable'];

        foreach (['billing', 'shipping'] as $block) {
            foreach (self::ADDRESS_FIELDS as $field) {
                $rules[$block.'.'.$field] = ['nullable'];
            }
        }

        return array_merge($rules, $this->customFieldRules());
    }

    /**
     * The row to persist: the allow-listed columns of the validated payload,
     * with authorship and tenancy stamped on top of whatever was sent.
     *
     * @return array<string, mixed>
     */
    public function customerAttributes(): array
    {
        $attributes = Arr::only($this->validated(), self::PERSISTED_FIELDS);

        $attributes['creator_id'] = $this->user()->id;
        $attributes['company_id'] = $this->header('company');

        return $attributes;
    }

    /**
     * The shipping block, ready for the addresses table.
     *
     * @return array<string, mixed>|null
     */
    public function shippingAddress(): ?array
    {
        return $this->addressBlock('shipping', Address::SHIPPING_TYPE);
    }

    /**
     * The billing block, ready for the addresses table.
     *
     * @return array<string, mixed>|null
     */
    public function billingAddress(): ?array
    {
        return $this->addressBlock('billing', Address::BILLING_TYPE);
    }

    /**
     * Custom-field values, or null when none came in. An empty array counts as
     * none, so the writer is never called for nothing.
     *
     * @return array<int, mixed>|null
     */
    public function customFields(): ?array
    {
        $values = $this->input('customFields');

        if (! is_array($values) || $values === []) {
            return null;
        }

        return $values;
    }

    /**
     * Optional, but once given it has to parse — internationalised domains
     * included — and be unclaimed by another contact of the same company.
     *
     * On an update carrying an address the contact being edited is excused
     * from that check. The verb test is exact, so the same payload sent as
     * PATCH would collide with the contact's own row; kept as it stands.
     *
     * @return array<int, mixed>
     */
    private function emailRules(): array
    {
        $unclaimed = Rule::unique('customers')->where('company_id', $this->header('company'));

        if ($this->email != null && $this->isMethod('PUT')) {
            $unclaimed->ignore($this->route('customer')->id);
        }

        return [new IdnEmail, 'nullable', $unclaimed];
    }

    /**
     * One address block tagged with its type, or null when the payload has no
     * such block — a block whose every field is null counts as no block.
     *
     * @return array<string, mixed>|null
     */
    private function addressBlock(string $key, string $type): ?array
    {
        $block = $this->input($key);

        if (! is_array($block) || Arr::where($block, fn ($value): bool => isset($value)) === []) {
            return null;
        }

        return array_merge($block, ['type' => $type]);
    }
}
