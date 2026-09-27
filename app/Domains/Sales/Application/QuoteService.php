<?php

namespace App\Domains\Sales\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use App\Domains\Sales\Contracts\QuoteEmailSender;
use App\Domains\Sales\Contracts\QuotePdfDataProvider;
use App\Domains\Sales\Models\Quote;
use App\Platform\Mail\Contracts\MailConfigurator;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class QuoteService extends SalesProposalService implements QuotePdfDataProvider
{
    public function __construct(DocumentItemService $items, MailConfigurator $mail, CustomFieldValueWriter $fields, DocumentExchangeRateRecorder $rates, private readonly QuoteEmailSender $sender)
    {
        parent::__construct($items, $mail, $fields, $rates);
    }

    protected function modelClass(): string
    {
        return Quote::class;
    }

    protected function sendEmail(array $data): void
    {
        $this->sender->send($data);
    }

    public function respond(Quote $quote, string $status): void
    {
        $zone = CompanySetting::getSetting('time_zone', $quote->company_id) ?: config('app.timezone');
        if ($quote->status === Quote::STATUS_DRAFT || $quote->status === Quote::STATUS_EXPIRED || ($quote->expiry_date && Carbon::parse($quote->expiry_date, $zone)->endOfDay()->isPast())) {
            throw ValidationException::withMessages(['status' => 'This quote is not open for a response.']);
        }
        $this->changeStatus($quote, $status);
    }

    public function sendQuoteData(Quote $document, array $data): array
    {
        return $this->sendData($document, $data);
    }

    public function recordView(Quote $quote): void
    {
        $changed = Quote::query()->whereKey($quote->id)->whereIn('status', [Quote::STATUS_DRAFT, Quote::STATUS_SENT])->update(['status' => Quote::STATUS_VIEWED]);
        if (! $changed) {
            return;
        }
        $quote->status = Quote::STATUS_VIEWED;
        if (CompanySetting::getSetting('notify_quote_viewed', $quote->company_id) === 'YES') {
            $recipient = CompanySetting::getSetting('notification_email', $quote->company_id);
            if ($recipient) {
                $this->sender->viewed(['quote' => $quote->toArray(), 'user' => $quote->customer->toArray()], $recipient);
            }
        }
    }
}
