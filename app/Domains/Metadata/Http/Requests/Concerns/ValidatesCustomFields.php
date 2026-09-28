<?php

namespace App\Domains\Metadata\Http\Requests\Concerns;

use App\Domains\Metadata\Application\CustomFieldAnswerValidator;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // Either identifier will do. The slug is the one an integrator
            // can write down: it survives a rename and is the same on every
            // install, where the id is neither.
            "{$key}.*.id" => array_merge(
                ["required_without:{$key}.*.slug", 'nullable', 'integer'],
                $this->customFieldUniquenessRule($key),
                [
                    Rule::exists('custom_fields', 'id')
                        ->where('company_id', $this->header('company')),
                ]
            ),
            "{$key}.*.slug" => array_merge(
                ["required_without:{$key}.*.id", 'nullable', 'string'],
                $this->customFieldUniquenessRule($key),
                [
                    Rule::exists('custom_fields', 'slug')
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
     * Check each answer against the definition that governs it.
     *
     * An `after` hook rather than a rule, for two reasons. Every definition
     * named by the payload is loaded in one query instead of one per answer.
     * And `rules()` stays static, so Scramble keeps deriving the documented
     * request shape from it.
     *
     * Requests call this from `withValidator()`, the same way they already
     * call the document tax check.
     */
    protected function validateCustomFieldAnswers(Validator $validator, string $key = 'customFields'): void
    {
        $validator->after(function (Validator $validator) use ($key): void {
            $answers = $this->input($key);

            if (! is_array($answers) || $answers === []) {
                return;
            }

            $company = $this->header('company');

            $definitions = CustomField::query()
                ->where('company_id', $company)
                ->where(function ($query) use ($answers) {
                    $query->whereIn('id', array_filter(Arr::pluck($answers, 'id')))
                        ->orWhereIn('slug', array_filter(Arr::pluck($answers, 'slug')));
                })
                ->get();

            $byId = $definitions->keyBy('id');
            $bySlug = $definitions->keyBy('slug');

            foreach ($answers as $index => $answer) {
                $definition = $byId->get($answer['id'] ?? null)
                    ?? $bySlug->get($answer['slug'] ?? null);

                if (! $definition) {
                    // Already reported by the exists rule on the id.
                    continue;
                }

                app(CustomFieldAnswerValidator::class)->validate(
                    $validator,
                    "{$key}.{$index}.value",
                    $answer['value'] ?? null,
                    $definition,
                    (int) $company
                );
            }
        });
    }

    /**
     * The attributes a record is saved with, minus the answers.
     *
     * Declaring the key in `rules()` puts it in `validated()`, and several
     * endpoints hand that straight to a model. Eloquent guards any key that
     * is not a real column, so nothing breaks today, but that is a framework
     * behaviour rather than an intention of ours: turning on
     * `Model::preventSilentlyDiscardingAttributes()` would make every one of
     * those endpoints throw. Saying it here means the answers never reach a
     * model whatever the framework decides to do about stray keys.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function withoutCustomFields(array $attributes): array
    {
        return Arr::except($attributes, ['customFields']);
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
