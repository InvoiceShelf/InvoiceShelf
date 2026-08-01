<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A single CSS length: a number and one of the units both PDF drivers accept.
 *
 * Used for page dimensions and margins, which are handed to Gotenberg as strings
 * and converted to points for dompdf. Empty values pass through so it composes
 * with `nullable`.
 */
class CssLength implements ValidationRule
{
    /** A bare `0` is valid CSS and the only length that needs no unit. */
    public const PATTERN = '/^(0|\d+(\.\d+)?(pt|px|pc|mm|cm|in))$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (! preg_match(self::PATTERN, trim($value))) {
            $fail('The :attribute must be 0, or a number followed by pt, px, pc, mm, cm or in (e.g. "210mm").');
        }
    }
}
