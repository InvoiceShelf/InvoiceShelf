<?php

namespace App\Platform\Storage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting a backup. Only the email choice is checked here; the option and
 * the disk are still read, and answered for, by the job.
 */
class CreateBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'notify' => ['sometimes', 'boolean'],
        ];
    }
}
