<?php

namespace App\Domains\Metadata\Http\Requests\Concerns;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Models\CustomField;
use Carbon\Carbon;
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

                $this->checkAnswer(
                    $validator,
                    "{$key}.{$index}.value",
                    $answer['value'] ?? null,
                    $definition
                );
            }
        });
    }

    /**
     * One answer against one definition.
     *
     * `is_required` is enforced here because it was enforced nowhere on the
     * server: it lived on the definition and only the browser honoured it, so
     * any caller could save a record with a required answer missing.
     */
    private function checkAnswer(Validator $validator, string $attribute, mixed $value, CustomField $definition): void
    {
        $label = $definition->label;

        // A switch turned off and a zero are answers; only nothing is not.
        $isBlank = $value === null || $value === '' || $value === [];

        if ($definition->is_required && $isBlank) {
            $validator->errors()->add($attribute, "{$label} is required.");

            return;
        }

        if ($isBlank) {
            return;
        }

        // A dropdown's option list is its rule and needs no configuring, but
        // nothing enforced it: the widget limits what can be picked in the
        // browser and the API accepted any string at all.
        $this->checkOptions($validator, $attribute, $value, $definition, $label);

        $rules = $definition->validation ?? [];

        if ($rules === []) {
            return;
        }

        $this->checkLength($validator, $attribute, $value, $rules, $label);
        $this->checkRange($validator, $attribute, $value, $rules, $label);
        $this->checkPattern($validator, $attribute, $value, $rules, $label);
        $this->checkDateRange($validator, $attribute, $value, $rules, $definition, $label);
    }

    /**
     * A dropdown answer has to be one of the options offered.
     *
     * Options have been stored both as plain strings and as `{name: ...}`
     * objects over the life of the column, so both are read.
     */
    private function checkOptions(Validator $validator, string $attribute, mixed $value, CustomField $definition, string $label): void
    {
        if ($definition->type !== 'Dropdown') {
            return;
        }

        $options = collect($definition->options ?? [])
            ->map(fn ($option) => is_array($option) ? ($option['name'] ?? null) : $option)
            ->filter()
            ->values();

        if ($options->isEmpty() || $options->contains($value)) {
            return;
        }

        $validator->errors()->add($attribute, "{$label} must be one of the options offered.");
    }

    /** @param  array<string, mixed>  $rules */
    private function checkLength(Validator $validator, string $attribute, mixed $value, array $rules, string $label): void
    {
        if (! is_scalar($value)) {
            return;
        }

        $length = mb_strlen((string) $value);

        if (isset($rules['min_length']) && $length < (int) $rules['min_length']) {
            $validator->errors()->add($attribute, "{$label} must be at least {$rules['min_length']} characters.");
        }

        if (isset($rules['max_length']) && $length > (int) $rules['max_length']) {
            $validator->errors()->add($attribute, "{$label} may not be longer than {$rules['max_length']} characters.");
        }
    }

    /** @param  array<string, mixed>  $rules */
    private function checkRange(Validator $validator, string $attribute, mixed $value, array $rules, string $label): void
    {
        if (! is_numeric($value)) {
            return;
        }

        if (isset($rules['min']) && $value + 0 < $rules['min'] + 0) {
            $validator->errors()->add($attribute, "{$label} may not be less than {$rules['min']}.");
        }

        if (isset($rules['max']) && $value + 0 > $rules['max'] + 0) {
            $validator->errors()->add($attribute, "{$label} may not be greater than {$rules['max']}.");
        }
    }

    /**
     * A date, datetime or time answer against the bounds its definition sets.
     *
     * A time of day is compared as text: both sides are zero-padded `H:i`,
     * which orders correctly without pretending a time of day is an instant.
     * Dates and datetimes are compared as instants in the company's own zone,
     * because `today` has to mean the company's today.
     *
     * @param  array<string, mixed>  $rules
     */
    private function checkDateRange(Validator $validator, string $attribute, mixed $value, array $rules, CustomField $definition, string $label): void
    {
        $earliest = $rules['earliest'] ?? null;
        $latest = $rules['latest'] ?? null;

        if (($earliest === null && $latest === null) || ! is_scalar($value)) {
            return;
        }

        if (! in_array($definition->type, ['Date', 'DateTime', 'Time'], true)) {
            return;
        }

        if ($definition->type === 'Time') {
            $answer = $this->asTimeOfDay((string) $value);

            if ($answer === null) {
                return;
            }

            if ($earliest && ($from = $this->asTimeOfDay((string) $earliest)) && $answer < $from) {
                $validator->errors()->add($attribute, "{$label} may not be earlier than {$from}.");
            }

            if ($latest && ($to = $this->asTimeOfDay((string) $latest)) && $answer > $to) {
                $validator->errors()->add($attribute, "{$label} may not be later than {$to}.");
            }

            return;
        }

        $zone = CompanySetting::timeZone($this->header('company'));
        $answer = $this->asInstant((string) $value, $zone);

        if ($answer === null) {
            return;
        }

        $from = $earliest === null ? null : $this->resolveBound((string) $earliest, $zone, upper: false);
        $to = $latest === null ? null : $this->resolveBound((string) $latest, $zone, upper: true);

        if ($from && $answer->lt($from)) {
            $validator->errors()->add($attribute, "{$label} may not be earlier than {$from->toDateString()}.");
        }

        if ($to && $answer->gt($to)) {
            $validator->errors()->add($attribute, "{$label} may not be later than {$to->toDateString()}.");
        }
    }

    /**
     * A bound as an instant.
     *
     * `today` is the company's today: the start of it for a lower bound and
     * the end for an upper one, so "latest: today" accepts any moment today
     * rather than only midnight.
     */
    private function resolveBound(string $bound, string $zone, bool $upper): ?Carbon
    {
        if ($bound === CustomField::BOUND_TODAY) {
            return $upper ? Carbon::today($zone)->endOfDay() : Carbon::today($zone)->startOfDay();
        }

        return $this->asInstant($bound, $zone);
    }

    /** A submitted or configured moment, or null when it is not one. */
    private function asInstant(string $value, string $zone): ?Carbon
    {
        try {
            return Carbon::parse($value, $zone);
        } catch (\Throwable) {
            // Unparseable: the column will refuse it, and a second complaint
            // about its range would only confuse.
            return null;
        }
    }

    /** A zero-padded `H:i`, which orders correctly as text, or null. */
    private function asTimeOfDay(string $value): ?string
    {
        return preg_match('/^([01]\\d|2[0-3]):[0-5]\\d/', $value, $matches) === 1
            ? substr($value, 0, 5)
            : null;
    }

    /** @param  array<string, mixed>  $rules */
    private function checkPattern(Validator $validator, string $attribute, mixed $value, array $rules, string $label): void
    {
        $pattern = $rules['pattern'] ?? null;

        if (! is_string($pattern) || $pattern === '' || ! is_scalar($value)) {
            return;
        }

        // False covers both an uncompilable pattern, which the definition
        // form rejects, and one that exhausted the backtrack limit. Neither
        // is a match, and neither should pass silently.
        if (@preg_match(CustomField::compilePattern($pattern), (string) $value) !== 1) {
            $validator->errors()->add($attribute, "{$label} is not in the expected format.");
        }
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
