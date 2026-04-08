<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UnzipUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'path' => [
                'required',
                'string',
                'max:512',
            ],
            'module' => [
                'required',
                'string',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $path = (string) $this->input('path');
            $path = trim($path);

            if ($path === '') {
                $validator->errors()->add('path', 'The path field is required.');

                return;
            }

            if (! str_ends_with($path, '.zip')) {
                $validator->errors()->add('path', 'The path must be a .zip file.');

                return;
            }

            $fullPath = $path;
            if (! str_starts_with($fullPath, DIRECTORY_SEPARATOR)) {
                $fullPath = storage_path('app/'.ltrim($fullPath, '/'));
            }

            $real = realpath($fullPath);
            if ($real === false || ! is_file($real)) {
                $validator->errors()->add('path', 'The ZIP file could not be found.');

                return;
            }

            $appStorage = realpath(storage_path('app'));
            if ($appStorage === false || ! str_starts_with($real, $appStorage.DIRECTORY_SEPARATOR)) {
                $validator->errors()->add('path', 'Invalid ZIP path.');

                return;
            }

            // Only allow paths we created during install flow.
            if (! preg_match('#/temp-[a-f0-9]{32}/upload\\.zip$#', str_replace('\\', '/', $real))) {
                $validator->errors()->add('path', 'Invalid ZIP path.');
            }
        });
    }
}
