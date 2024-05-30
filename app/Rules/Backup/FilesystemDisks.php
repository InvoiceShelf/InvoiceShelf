<?php

namespace App\Rules\Backup;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FilesystemDisks implements ValidationRule
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
        $configuredFileSystemDisks = array_keys(config('filesystems.disks'));

        if (! in_array($value, $configuredFileSystemDisks)) {
            $fail('This disk is not configured as a filesystem disk.');
        }
    }
}
