<?php

namespace App\Platform\Mail\Http\Requests;

use App\Platform\Mail\Application\MailConfigurationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyMailConfigurationRequest extends FormRequest
{
    /**
     * Authorization is enforced by the controller, so let the request past.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rule set for the submitted payload, keyed off the custom-config toggle.
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
            allowDisabledCustomConfig: true,
            allowPrivateHosts: (bool) $this->user()?->isSuperAdmin(),
        );
    }
}
