<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * The form behind editing the company the request header points at.
 *
 * The name has to stay unique across the whole installation, the company being
 * edited excepted — and it is excepted by the header value, not by a routed
 * model, because this endpoint has no route parameter to work from.
 */
class CompanyRequest extends FormRequest
{
    use ValidatesCustomFields;

    /** Columns lifted off the validated payload onto the company row. */
    private const COMPANY_FIELDS = [
        'name',
        'vat_id',
        'tax_id',
    ];

    /**
     * Ownership is settled by the gate in the controller, so every caller that
     * got this far is let through.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The two tax identifiers are declared but unchecked; the country of the
     * address block is required whether or not an address was submitted.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                $this->unclaimedName(),
            ],
            'vat_id' => [
                'nullable',
            ],
            'tax_id' => [
                'nullable',
            ],
            'address.country_id' => [
                'required',
            ],
            ...$this->customFieldRules(),
        ];
    }

    /**
     * The name has to be free across the whole installation, with one company
     * excused: the one named by the request header.
     *
     * The exception is keyed on the header value rather than on a loaded model
     * — nothing here checks that the header names a company that exists, so a
     * header pointing at nothing simply excuses no row at all.
     */
    private function unclaimedName(): Unique
    {
        return Rule::unique('companies')->ignore($this->header('company'), 'id');
    }

    /**
     * The columns to write, with a slug rebuilt from the submitted name.
     *
     * Both tax identifiers are declared as nullable rules here, so unlike the
     * creation form they do survive into the validated payload and are written
     * through. The slug is derived from the raw input rather than the
     * validated set, which is the same string either way.
     */
    public function getCompanyPayload()
    {
        return array_merge(
            Arr::only($this->validated(), self::COMPANY_FIELDS),
            ['slug' => Str::slug($this->name)]
        );
    }
}
