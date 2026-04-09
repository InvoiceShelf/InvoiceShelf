<?php

namespace App\Http\Requests;

use App\Services\MailConfigurationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyMailConfigurationRequest extends FormRequest
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
        if ($this->string('use_custom_mail_config')->toString() !== 'YES') {
            return [
                'use_custom_mail_config' => ['required', 'string', Rule::in(['YES', 'NO'])],
                'mail_driver' => ['nullable', 'string'],
            ];
        }

        return app(MailConfigurationService::class)->validationRules(
            $this->string('mail_driver')->toString(),
            true
        );
    }
}
