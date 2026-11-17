<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\EmailLog;
use App\Models\Estimate;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The current link of an email that went out with an old link, sent to the
 * address that email went to (see LegacyEmailLinkController), in the
 * company's language.
 */
class LegacyEmailLinkMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public EmailLog $emailLog, public string $url)
    {
        $this->locale(CompanySetting::getSetting('language', $emailLog->mailable->company_id) ?: config('app.locale'));
    }

    public function build(): static
    {
        $document = $this->emailLog->mailable;
        $company = $document->company;

        [$subject, $intro, $button, $number] = match (true) {
            $document instanceof Invoice => [
                'New link to invoice :number from :company',
                'You asked for a new link to invoice :number from :company. The link in the earlier email no longer works, because links in emails sent before a security update were replaced.',
                'mail_view_invoice',
                $document->invoice_number,
            ],
            $document instanceof Estimate => [
                'New link to estimate :number from :company',
                'You asked for a new link to estimate :number from :company. The link in the earlier email no longer works, because links in emails sent before a security update were replaced.',
                'mail_view_estimate',
                $document->estimate_number,
            ],
            default => [
                'New link to payment receipt :number from :company',
                'You asked for a new link to payment receipt :number from :company. The link in the earlier email no longer works, because links in emails sent before a security update were replaced.',
                'mail_view_payment',
                $document->payment_number,
            ],
        };

        $replace = ['number' => $number, 'company' => $company->name];

        return $this->from($this->emailLog->from ?: config('mail.from.address'), config('mail.from.name'))
            ->subject(__($subject, $replace))
            ->markdown('emails.legacy-link', [
                'companyName' => $company->name,
                'intro' => __($intro, $replace),
                'button' => __($button),
                'expiresOn' => $this->expiresOn($company->id),
            ]);
    }

    /**
     * The last day the link works, in the company's date format, when the
     * company lets links expire. Mirrors EmailLog::isExpired().
     */
    private function expiresOn(int $companyId): ?string
    {
        if (CompanySetting::getSetting('automatically_expire_public_links', $companyId) !== 'YES') {
            return null;
        }

        $days = (int) CompanySetting::getSetting('link_expiry_days', $companyId);
        $format = CompanySetting::getSetting('carbon_date_format', $companyId) ?: 'Y-m-d';

        return $this->emailLog->created_at->copy()->addDays($days)->translatedFormat($format);
    }
}
