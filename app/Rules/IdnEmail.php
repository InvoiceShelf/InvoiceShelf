<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates email addresses including those with internationalized
 * domain names (IDN) like user@münchen.de or michel@exempleé.fr.
 *
 * Converts the domain part to Punycode (ASCII) before validation.
 */
class IdnEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $parts = explode('@', $value);

        if (count($parts) !== 2) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        [$local, $domain] = $parts;

        if (function_exists('idn_to_ascii') && $domain !== '') {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii !== false) {
                $domain = $ascii;
            }
        }

        $normalized = $local.'@'.$domain;

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            $fail('The :attribute must be a valid email address.');
        }
    }
}
