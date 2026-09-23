<?php

namespace App\Domains\Sales\Application\Composition;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Support\SpaTranslations;

/**
 * What the send estimate dialog prefills, keyed the way the send endpoint takes
 * it: the configured sender, the customer's address, the translated
 * "New Estimate" subject and the company's estimate mail body.
 */
final class EstimateMailDefaults
{
    public function __construct(
        private readonly MailConfigurator $mailConfigurator,
    ) {}

    /**
     * @return array{from: mixed, to: string|null, cc: null, bcc: null, subject: string, body: string|null}
     */
    public function for(Estimate $estimate, string $locale): array
    {
        return [
            'from' => $this->mailConfigurator->getDefaultConfig()['from_mail'] ?? null,
            'to' => $estimate->customer?->email,
            'cc' => null,
            'bcc' => null,
            'subject' => SpaTranslations::get($locale, 'estimates.new_estimate'),
            'body' => CompanySetting::getSetting('estimate_mail_body', $estimate->company_id),
        ];
    }
}
