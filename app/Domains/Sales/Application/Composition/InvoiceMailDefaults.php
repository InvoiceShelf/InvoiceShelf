<?php

namespace App\Domains\Sales\Application\Composition;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Support\SpaTranslations;

/**
 * What the send invoice dialog prefills, keyed the way the send endpoint takes
 * it: the configured sender, the customer's address, the translated
 * "New Invoice" subject and the company's invoice mail body.
 */
final class InvoiceMailDefaults
{
    public function __construct(
        private readonly MailConfigurator $mailConfigurator,
    ) {}

    /**
     * @return array{from: mixed, to: string|null, cc: null, bcc: null, subject: string, body: string|null}
     */
    public function for(Invoice $invoice, string $locale): array
    {
        return [
            'from' => $this->mailConfigurator->getDefaultConfig()['from_mail'] ?? null,
            'to' => $invoice->customer?->email,
            'cc' => null,
            'bcc' => null,
            'subject' => SpaTranslations::get($locale, 'invoices.new_invoice'),
            'body' => CompanySetting::getSetting('invoice_mail_body', $invoice->company_id),
        ];
    }
}
