<?php

namespace App\Domains\Metadata\Http\Requests;

use App\Domains\Metadata\Application\CustomFieldModelCatalog;
use App\Domains\Metadata\Models\CustomField;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A custom-field definition as the admin screen submits it.
 *
 * `model_type` is checked against the catalogue, which the editor's dropdown
 * is also built from, so the two cannot drift and an unknown model can no
 * longer reach the column. The default answer never appears below: which
 * value column it belongs in follows from `type`, so the controller reads it
 * off the request untouched and lets the service place it.
 */
class CustomFieldRequest extends FormRequest
{
    /**
     * Every caller is welcome here; the controller is what consults the policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'label' => ['required'],
            'model_type' => ['required', Rule::in(app(CustomFieldModelCatalog::class)->keys())],
            'order' => ['required'],
            'type' => ['required'],
            'is_required' => ['required', 'boolean'],
            'options' => ['array', 'nullable'],
            'placeholder' => ['string', 'nullable'],
            'placement' => ['sometimes', Rule::in([
                CustomField::PLACEMENT_INTERNAL,
                CustomField::PLACEMENT_DOCUMENT,
            ])],
            'validation' => ['sometimes', 'nullable', 'array'],
            'validation.min_length' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'validation.max_length' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'validation.min' => ['sometimes', 'nullable', 'numeric'],
            'validation.max' => ['sometimes', 'nullable', 'numeric'],
            'validation.earliest' => ['sometimes', 'nullable', 'string', 'max:40'],
            'validation.latest' => ['sometimes', 'nullable', 'string', 'max:40'],
            'validation.pattern' => [
                'sometimes',
                'nullable',
                'string',
                'max:'.CustomField::MAX_PATTERN_LENGTH,
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateBoundsAreOrdered($validator);
            $this->validateDateBounds($validator);
            $this->validatePatternCompiles($validator);
        });
    }

    /**
     * A maximum below its minimum describes nothing an answer could satisfy.
     */
    private function validateBoundsAreOrdered(Validator $validator): void
    {
        foreach ([['min_length', 'max_length'], ['min', 'max']] as [$low, $high]) {
            $from = $this->input("validation.{$low}");
            $to = $this->input("validation.{$high}");

            if ($from !== null && $to !== null && $to < $from) {
                $validator->errors()->add(
                    "validation.{$high}",
                    "The {$high} may not be less than the {$low}."
                );
            }
        }
    }

    /**
     * A date bound is either the word `today` or a moment we can read, and
     * the later one may not precede the earlier.
     *
     * Checked when the definition is written rather than when somebody tries
     * to answer it, for the same reason the pattern is.
     */
    private function validateDateBounds(Validator $validator): void
    {
        $resolved = [];

        foreach (['earliest', 'latest'] as $key) {
            $bound = $this->input("validation.{$key}");

            if (! is_string($bound) || $bound === '') {
                continue;
            }

            if ($bound === CustomField::BOUND_TODAY) {
                continue;
            }

            try {
                $resolved[$key] = Carbon::parse($bound);
            } catch (\Throwable) {
                $validator->errors()->add("validation.{$key}", 'This is not a date we can read.');
            }
        }

        if (isset($resolved['earliest'], $resolved['latest'])
            && $resolved['latest']->lt($resolved['earliest'])) {
            $validator->errors()->add('validation.latest', 'The latest may not be before the earliest.');
        }
    }

    /**
     * Reject a pattern PCRE will not accept.
     *
     * Caught here, when it is written, rather than later when somebody tries
     * to answer the field and cannot understand why nothing they type is
     * allowed. The delimiters are ours, so the author writes the expression
     * alone and cannot smuggle in modifiers.
     */
    private function validatePatternCompiles(Validator $validator): void
    {
        $pattern = $this->input('validation.pattern');

        if (! is_string($pattern) || $pattern === '') {
            return;
        }

        if (@preg_match(CustomField::compilePattern($pattern), '') === false) {
            $validator->errors()->add('validation.pattern', 'This is not a valid pattern.');
        }
    }
}
