<?php

namespace App\Platform\Mail\Http\Requests;

use App\Platform\Mail\Application\MailConfigurationService;
use App\Platform\Operations\Managed\ManagedMode;
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
            // On a managed install the super administrator is the customer,
            // so nobody gets the private network.
            allowPrivateHosts: ! ManagedMode::enabled() && (bool) $this->user()?->isSuperAdmin(),
            managed: ManagedMode::enabled(),
        );
    }

    /**
     * The managed install's port and encryption rules name what they accept.
     */
    public function messages(): array
    {
        if (! ManagedMode::enabled()) {
            return [];
        }

        return [
            'mail_port.in' => 'The :attribute must be 465, 587 or 2525.',
            'mail_encryption.required' => 'The :attribute must be TLS or SSL.',
            'mail_encryption.in' => 'The :attribute must be TLS or SSL.',
        ];
    }
}
