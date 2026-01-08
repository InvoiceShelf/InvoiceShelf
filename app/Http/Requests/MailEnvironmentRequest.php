<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MailEnvironmentRequest extends FormRequest
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
        switch ($this->get('mail_driver')) {
            case 'smtp':
                return [
                    'mail_driver' => [
                        'required',
                        'string',
                    ],
                    'mail_host' => [
                        'required',
                        'string',
                    ],
                    'mail_port' => [
                        'required',
                    ],
                    'mail_username' => [
                        'nullable',
                        'string',
                    ],
                    'mail_password' => [
                        'nullable',
                        'string',
                    ],
                    'mail_encryption' => [
                        'nullable',
                        'string',
                    ],
                    'mail_scheme' => [
                        'nullable',
                        'string',
                    ],
                    'mail_url' => [
                        'nullable',
                        'string',
                    ],
                    'mail_timeout' => [
                        'nullable',
                        'integer',
                    ],
                    'mail_local_domain' => [
                        'nullable',
                        'string',
                    ],
                    'from_name' => [
                        'required',
                        'string',
                    ],
                    'from_mail' => [
                        'required',
                        'string',
                        'email',
                    ],
                ];

            case 'mailgun':
                return [
                    'mail_driver' => [
                        'required',
                        'string',
                    ],
                    'mail_mailgun_domain' => [
                        'required',
                        'string',
                    ],
                    'mail_mailgun_secret' => [
                        'required',
                        'string',
                    ],
                    'mail_mailgun_endpoint' => [
                        'nullable',
                        'string',
                    ],
                    'mail_mailgun_scheme' => [
                        'nullable',
                        'string',
                    ],
                    'from_name' => [
                        'required',
                        'string',
                    ],
                    'from_mail' => [
                        'required',
                        'string',
                        'email',
                    ],
                ];

            case 'ses':
                return [
                    'mail_driver' => [
                        'required',
                        'string',
                    ],
                    'mail_ses_key' => [
                        'required',
                        'string',
                    ],
                    'mail_ses_secret' => [
                        'required',
                        'string',
                    ],
                    'mail_ses_region' => [
                        'nullable',
                        'string',
                    ],
                    'from_name' => [
                        'required',
                        'string',
                    ],
                    'from_mail' => [
                        'required',
                        'string',
                        'email',
                    ],
                ];

            case 'sendmail':
                return [
                    'mail_driver' => [
                        'required',
                        'string',
                    ],
                    'mail_sendmail_path' => [
                        'nullable',
                        'string',
                    ],
                    'from_name' => [
                        'required',
                        'string',
                    ],
                    'from_mail' => [
                        'required',
                        'string',
                        'email',
                    ],
                ];

            default:
                return [
                    'mail_driver' => [
                        'required',
                        'string',
                    ],
                    'from_name' => [
                        'required',
                        'string',
                    ],
                    'from_mail' => [
                        'required',
                        'string',
                        'email',
                    ],
                ];
        }
    }
}
