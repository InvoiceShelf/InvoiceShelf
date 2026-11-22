<?php

namespace App\Domains\Taxation\Http\Requests;

use App\Domains\Taxation\Models\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Incoming payload for creating or editing a hand-maintained tax type.
 *
 * Beyond the field rules it does two things: it decides whether the compound
 * flag is allowed by looking at the values the row will *end up* with — the
 * submitted ones, falling back to what is stored when an edit stays silent
 * about a field — and it produces the attribute array to persist, pinned to
 * the GENERAL kind and to the company the request was addressed to.
 */
class TaxTypeRequest extends FormRequest
{
    /**
     * Access is settled by the tax-type policy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $name = Rule::unique('tax_types')
            ->where('type', TaxType::TYPE_GENERAL)
            ->where('company_id', $this->header('company'));

        // Quirk kept as is: only PUT excuses the edited row from the name
        // check. A PATCH would collide with its own stored name.
        if ($this->isMethod('PUT')) {
            $name->ignore($this->route('tax_type')->id);
        }

        return [
            'name' => [
                'required',
                $name,
            ],
            'calculation_type' => [
                'required',
                Rule::in(['percentage', 'fixed']),
            ],
            'percent' => [
                'nullable',
                'numeric',
            ],
            'fixed_amount' => [
                'nullable',
                'numeric',
            ],
            'description' => [
                'nullable',
            ],
            'compound_tax' => [
                'sometimes',
                'boolean',
            ],
            'collective_tax' => [
                'nullable',
            ],
            'transaction_type' => [
                'sometimes',
                Rule::in([
                    TaxType::TRANSACTION_TYPE_SALES,
                    TaxType::TRANSACTION_TYPE_PURCHASES,
                ]),
            ],
            'tax_category_code' => [
                'sometimes',
                'required',
                'string',
                Rule::in(TaxType::TAX_CATEGORY_CODES),
            ],
            'tax_exemption_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Compounding is reserved for percentage taxes on the sales side.
     *
     * The check is skipped while any other rule has already failed, so a
     * request with, say, a bad calculation type reports that alone.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->compoundFlag()) {
                return;
            }

            $allowed = $this->settledValue('calculation_type', 'percentage') === 'percentage'
                && $this->settledValue('transaction_type', TaxType::TRANSACTION_TYPE_SALES)
                    === TaxType::TRANSACTION_TYPE_SALES;

            if (! $allowed) {
                $validator->errors()->add(
                    'compound_tax',
                    'Compound tax is only available for percentage sales taxes.'
                );
            }
        });

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->requiresTaxExemptionReason()) {
                return;
            }

            if (trim((string) $this->effectiveTaxExemptionReason()) === '') {
                $validator->errors()->add(
                    'tax_exemption_reason',
                    'An exemption reason is required for exempt tax category codes.'
                );
            }
        });
    }

    /**
     * Attributes to write, with the gaps a legacy client leaves filled in.
     */
    public function getTaxTypePayload()
    {
        $payload = $this->validated();

        if (! array_key_exists('transaction_type', $payload)) {
            $payload['transaction_type'] = $this->isEdit()
                ? $this->storedValue('transaction_type')
                : TaxType::TRANSACTION_TYPE_SALES;
        }

        // An edit that omits the flag keeps whatever the row already holds;
        // a create that omits it is plainly not compound.
        if (! $this->isEdit() && ! array_key_exists('compound_tax', $payload)) {
            $payload['compound_tax'] = false;
        }

        $payload['tax_category_code'] = $this->effectiveTaxCategoryCode();
        $payload['tax_exemption_reason'] = $this->requiresTaxExemptionReason()
            ? $this->effectiveTaxExemptionReason()
            : null;

        $payload['company_id'] = $this->header('company');
        $payload['type'] = TaxType::TYPE_GENERAL;

        return $payload;
    }

    /**
     * Whether the row will carry the compound flag once written.
     */
    private function compoundFlag(): bool
    {
        if ($this->has('compound_tax')) {
            return $this->boolean('compound_tax');
        }

        return $this->isEdit() ? $this->storedValue('compound_tax') : false;
    }

    /**
     * The value a field will hold after the write: what was sent, else the
     * stored value on an edit, else the create-time default.
     */
    private function settledValue(string $field, string $default): ?string
    {
        if ($this->has($field)) {
            return $this->input($field);
        }

        return $this->isEdit() ? $this->storedValue($field) : $default;
    }

    /**
     * The Tax Category Code the request results in — the submitted one, the
     * stored one on edits that omit it, or the standard rate on creates.
     */
    private function effectiveTaxCategoryCode(): string
    {
        if ($this->has('tax_category_code')) {
            return (string) $this->input('tax_category_code');
        }

        return $this->isEdit()
            ? ($this->storedValue('tax_category_code') ?? TaxType::TAX_CATEGORY_CODE_STANDARD)
            : TaxType::TAX_CATEGORY_CODE_STANDARD;
    }

    /**
     * The exemption reason the request results in — the submitted one, or the
     * stored one on edits that omit it.
     */
    private function effectiveTaxExemptionReason(): ?string
    {
        if ($this->has('tax_exemption_reason')) {
            return $this->input('tax_exemption_reason');
        }

        return $this->isEdit() ? $this->storedValue('tax_exemption_reason') : null;
    }

    /**
     * Whether EN 16931 demands an exemption reason for the resulting category.
     */
    private function requiresTaxExemptionReason(): bool
    {
        return in_array($this->effectiveTaxCategoryCode(), TaxType::EXEMPT_TAX_CATEGORY_CODES, true);
    }

    private function storedValue(string $field)
    {
        return $this->route('tax_type')->{$field};
    }

    private function isEdit(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }
}
