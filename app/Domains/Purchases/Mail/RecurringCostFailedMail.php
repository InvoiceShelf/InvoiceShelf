<?php

namespace App\Domains\Purchases\Mail;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Purchases\Models\RecurringCost;
use App\Support\SpaTranslations;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A recurring schedule could not generate its bill or expense. Sent once when
 * the failures begin, not on every retry.
 */
class RecurringCostFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RecurringCost $schedule,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('Recurring :name could not be generated', ['name' => $this->schedule->name]),
        );
    }

    public function content(): Content
    {
        $locale = CompanySetting::getSetting('language', $this->schedule->company_id) ?: 'en';

        return new Content(
            markdown: 'emails.recurring-cost-failed',
            with: [
                'scheduleName' => $this->schedule->name,
                'reason' => SpaTranslations::get($locale, 'errors.'.$this->reason),
                'url' => url("/admin/recurring-costs/{$this->schedule->id}/view"),
            ],
        );
    }
}
