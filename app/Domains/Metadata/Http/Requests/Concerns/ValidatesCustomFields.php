<?php

namespace App\Domains\Metadata\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Validation for the custom-field answers a record is saved with.
 *
 * Nine endpoints accept them and none declared a rule, so the payload was
 * neither checked nor, since Scramble reads its request schemas from
 * `rules()`, documented. An id that named nothing, or another company's
 * definition, was dropped by the writer without a word, which reads exactly
 * like a value that saved.
 */
trait ValidatesCustomFields
{
    /**
     * Rules for one array of answers.
     *
     * The key is a parameter because a document carries its own answers under
     * `customFields` while each of its lines carries theirs under
     * `items.*.custom_fields`.
     *
     * Only `id` and `value` are constrained. The admin interface posts each
     * answer as the whole definition with a `value` added, so it also carries
     * a label, a type, options and the rest; rejecting those would reject the
     * application's own requests.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function customFieldRules(string $key = 'customFields'): array
    {
        return [
            $key => ['sometimes', 'array'],
            "{$key}.*" => ['required', 'array'],
            "{$key}.*.id" => array_merge(
                ['required', 'integer'],
                $this->customFieldUniquenessRule($key),
                [
                    Rule::exists('custom_fields', 'id')
                        ->where('company_id', $this->header('company')),
                ]
            ),
            // Present rather than required: an answer may legitimately be
            // cleared, and a switch turned off is a falsy value, not a
            // missing one.
            "{$key}.*.value" => ['present'],
        ];
    }

    /**
     * Answering the same definition twice on one record is a bug, but only
     * within that record.
     *
     * `distinct` compares every value the wildcard expands to, and for a
     * nested key that is every line on the document at once, which would
     * reject two lines carrying the same field -- the ordinary case for a
     * printed column. So it applies to the flat key only.
     *
     * @return array<int, string>
     */
    private function customFieldUniquenessRule(string $key): array
    {
        return str_contains($key, '*') ? [] : ['distinct'];
    }
}
