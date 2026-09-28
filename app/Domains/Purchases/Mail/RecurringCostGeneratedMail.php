<?php

namespace App\Domains\Purchases\Mail;

use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\RecurringCost;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A recurring schedule generated a bill or an expense.
 */
class RecurringCostGeneratedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RecurringCost $schedule,
        public readonly Model $record,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('Recurring :name generated', ['name' => $this->schedule->name]),
        );
    }

    public function content(): Content
    {
        $isBill = $this->record instanceof Bill;

        return new Content(
            markdown: 'emails.recurring-cost-generated',
            with: [
                'scheduleName' => $this->schedule->name,
                'recordLabel' => $isBill ? __('Bill :number', ['number' => $this->record->number]) : __('Expense'),
                'isDraft' => $isBill && $this->record->status === 'DRAFT',
                'date' => $isBill ? $this->record->document_date : $this->record->expense_date,
                'amount' => strip_tags(format_money_pdf($isBill ? $this->record->total : $this->record->amount, $this->record->currency)),
                'url' => url($isBill ? "/admin/bills/{$this->record->id}/view" : "/admin/expenses/{$this->record->id}/edit"),
            ],
        );
    }
}
