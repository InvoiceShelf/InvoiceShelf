<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Accounts\Application\MemberVisibleSettings;
use App\Domains\Sales\Application\InvoicePaymentLinks;
use App\Rules\PaymentLink;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The payload behind writing preferences: one map of option names to values.
 *
 * Values are not constrained, apart from the invoice payment link, which is
 * printed for customers to click and so must be a web address; the currency
 * is guarded by the controller once the books are open. The names are, a
 * little: the mail transport and module settings are refused, because each
 * has an endpoint of its own that validates it.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * Owner-only, but the gate that says so runs in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'settings' => [
                'bail',
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $visibility = app(MemberVisibleSettings::class);
                    $refused = array_filter(array_keys($value), fn (int|string $key): bool => $visibility->isPrivate((string) $key));

                    if ($refused !== []) {
                        $fail('These settings have their own page and cannot be changed here: '.implode(', ', $refused).'.');
                    }
                },
            ],
            'settings.'.InvoicePaymentLinks::SETTING => ['nullable', 'string', 'max:'.InvoicePaymentLinks::MAX_LENGTH, new PaymentLink],
            'settings.'.InvoicePaymentLinks::QR_SETTING => ['sometimes', Rule::in(['YES', 'NO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'settings.'.InvoicePaymentLinks::SETTING => 'payment link',
            'settings.'.InvoicePaymentLinks::QR_SETTING => 'QR code setting',
        ];
    }

    /**
     * An emptied payment link arrives as null (ConvertEmptyStringsToNull),
     * and a setting cannot hold null, so it is stored as an empty string:
     * no link.
     */
    protected function passedValidation(): void
    {
        $settings = $this->input('settings');

        if (array_key_exists(InvoicePaymentLinks::SETTING, $settings) && $settings[InvoicePaymentLinks::SETTING] === null) {
            $this->merge(['settings' => [...$settings, InvoicePaymentLinks::SETTING => '']]);
        }
    }
}
