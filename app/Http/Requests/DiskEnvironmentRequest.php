<?php

namespace App\Http\Requests;

use App\Models\FileDisk;
use App\Rules\SafeRemoteUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiskEnvironmentRequest extends FormRequest
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
        $rules = [];
        switch ($this->get('driver')) {
            case 's3':
                $rules = [
                    'credentials.key' => [
                        'required',
                        'string',
                    ],
                    'credentials.secret' => [
                        'required',
                        'string',
                    ],
                    'credentials.region' => [
                        'required',
                        'string',
                    ],
                    'credentials.bucket' => [
                        'required',
                        'string',
                    ],
                    'credentials.root' => [
                        'required',
                        'string',
                    ],
                ];

                break;

            case 'doSpaces':
                $rules = [
                    'credentials.key' => [
                        'required',
                        'string',
                    ],
                    'credentials.secret' => [
                        'required',
                        'string',
                    ],
                    'credentials.region' => [
                        'required',
                        'string',
                    ],
                    'credentials.bucket' => [
                        'required',
                        'string',
                    ],
                    'credentials.endpoint' => [
                        'required',
                        'string',
                        new SafeRemoteUrl,
                    ],
                    'credentials.root' => [
                        'required',
                        'string',
                    ],
                ];

                break;

            case 's3compat':
                $rules = [
                    'credentials.endpoint' => [
                        'required',
                        'string',
                        new SafeRemoteUrl,
                    ],
                    'credentials.key' => [
                        'required',
                        'string',
                    ],
                    'credentials.secret' => [
                        'required',
                        'string',
                    ],
                    'credentials.region' => [
                        'required',
                        'string',
                    ],
                    'credentials.bucket' => [
                        'required',
                        'string',
                    ],
                ];

                break;

            case 'dropbox':
                $rules = [
                    'credentials.token' => [
                        'required',
                        'string',
                    ],
                    'credentials.key' => [
                        'required',
                        'string',
                    ],
                    'credentials.secret' => [
                        'required',
                        'string',
                    ],
                    'credentials.app' => [
                        'required',
                        'string',
                    ],
                    'credentials.root' => [
                        'required',
                        'string',
                    ],
                ];

                break;
        }

        $defaultRules = [
            'name' => [
                'required',
            ],
            'driver' => [
                'required',
                Rule::in(FileDisk::DRIVERS),
            ],
        ];

        return array_merge($rules, $defaultRules);
    }
}
