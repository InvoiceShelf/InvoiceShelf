<?php

namespace App\Rules\Backup;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class PathToZip implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Initialization, if needed
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Str::endsWith($value, '.zip')) {
            $fail('The given value must be a path to a zip file.');
        }
    }
}
