<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Sales\Application\QuoteService;
use App\Domains\Sales\Http\Resources\CustomerPortal\QuoteResource;
use App\Domains\Sales\Models\Quote;
use App\Platform\Http\Controller;
use App\Platform\Mail\Models\EmailLog;
use Illuminate\Http\Request;

class QuotePdfController extends Controller
{
    /**
     * Stream the offer behind an emailed link. Opening it counts as reading
     * the offer, so the document is marked seen on the way through.
     */
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $quote = $this->documentBehind($emailLog);

        $this->recordReading($quote);

        return $quote->getGeneratedPDFOrStream('quote');
    }

    /**
     * Serve the same offer as JSON for the viewer shell.
     *
     * Only the customer portal representation is exposed, including printed
     * custom fields and excluding staff-only metadata.
     */
    public function getQuote(EmailLog $emailLog)
    {
        return QuoteResource::make($this->documentBehind($emailLog));
    }

    /**
     * Trade an email-log token for the offer it was issued for.
     *
     * Holding the token is the whole credential, so the guard is narrow: the
     * log must point at an offer, and the link must still be inside the
     * company's expiry window.
     */
    private function documentBehind(EmailLog $emailLog): Quote
    {
        $document = $emailLog->mailable;

        if (! $document instanceof Quote) {
            abort(404);
        }

        if ($emailLog->isExpired()) {
            abort(403, 'Link Expired.');
        }

        return $document;
    }

    /**
     * Promote an offer that is still awaiting a reader, and tell the issuer
     * about it when they asked to be told.
     */
    private function recordReading(Quote $quote): void
    {
        app(QuoteService::class)->recordView($quote);
    }
}
