<?php

namespace App\Rules\Backup;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BackupDisk implements ValidationRule
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
        $configuredBackupDisks = config('backup.backup.destination.disks');

        if (! in_array($value, $configuredBackupDisks)) {
            $fail('This disk is not configured as a backup disk.');
        }
    }
}
