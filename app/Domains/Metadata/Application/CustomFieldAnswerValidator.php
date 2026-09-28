<?php

namespace App\Domains\Metadata\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Models\CustomField;
use Carbon\Carbon;
use Illuminate\Validation\Validator;

/** Answer constraints shared by HTTP requests and background document generation. */
class CustomFieldAnswerValidator
{
    /**
     * One answer against one definition.
     *
     * `is_required` is enforced here because it was enforced nowhere on the
     * server: it lived on the definition and only the browser honoured it, so
     * any caller could save a record with a required answer missing.
     */
    public function validate(
        Validator $validator,
        string $attribute,
        mixed $value,
        CustomField $definition,
        int $companyId,
    ): void {
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
        $this->checkDateRange($validator, $attribute, $value, $rules, $definition, $label, $companyId);
    }

    /**
     * A dropdown answer has to be one of the options offered.
     *
     * Options have been stored both as plain strings and as `{name: ...}`
     * objects over the life of the column, so both are read.
     */
    private function checkOptions(
        Validator $validator,
        string $attribute,
        mixed $value,
        CustomField $definition,
        string $label,
    ): void {
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
    private function checkLength(
        Validator $validator,
        string $attribute,
        mixed $value,
        array $rules,
        string $label,
    ): void {
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
    private function checkRange(
        Validator $validator,
        string $attribute,
        mixed $value,
        array $rules,
        string $label,
    ): void {
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
    private function checkDateRange(
        Validator $validator,
        string $attribute,
        mixed $value,
        array $rules,
        CustomField $definition,
        string $label,
        int $companyId,
    ): void {
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

        $zone = CompanySetting::timeZone($companyId);
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
    private function checkPattern(
        Validator $validator,
        string $attribute,
        mixed $value,
        array $rules,
        string $label,
    ): void {
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
}
