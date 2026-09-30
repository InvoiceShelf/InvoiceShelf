<?php

namespace App\Rules;

use App\Support\Recurrence\Cadence;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A recurring schedule's frequency: a cron expression the scheduler can read.
 * Empty values pass so the rule composes with `required` or `nullable`.
 */
class CronFrequency implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! Cadence::isValid(is_string($value) ? $value : null)) {
            $fail('recurrence_frequency_invalid');
        }
    }
}
