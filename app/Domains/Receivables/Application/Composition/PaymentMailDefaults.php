<?php

namespace App\Domains\Receivables\Application\Composition;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Support\SpaTranslations;

/**
 * What the send payment dialog prefills, keyed the way the send endpoint takes
 * it: the configured sender, the customer's address, the translated
 * "New Payment" subject and the company's payment mail body.
 */
final class PaymentMailDefaults
{
    public function __construct(
        private readonly MailConfigurator $mailConfigurator,
    ) {}

    /**
     * @return array{from: mixed, to: string|null, cc: null, bcc: null, subject: string, body: string|null}
     */
    public function for(Payment $payment, string $locale): array
    {
        return [
            'from' => $this->mailConfigurator->getDefaultConfig()['from_mail'] ?? null,
            'to' => $payment->customer?->email,
            'cc' => null,
            'bcc' => null,
            'subject' => SpaTranslations::get($locale, 'payments.new_payment'),
            'body' => CompanySetting::getSetting('payment_mail_body', $payment->company_id),
        ];
    }
}
