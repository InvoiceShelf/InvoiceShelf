<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Http\Resources\EstimateResource;
use App\Domains\Sales\Mail\EstimateViewedMail;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Http\Controller;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Operations\Managed\ManagedMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EstimatePdfController extends Controller
{
    /**
     * Stream the offer behind an emailed link. Opening it counts as reading
     * the offer, so the document is marked seen on the way through.
     */
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $estimate = $this->documentBehind($emailLog);

        $this->recordReading($estimate);

        return $estimate->getGeneratedPDFOrStream('estimate');
    }

    /**
     * Serve the same offer as JSON for the viewer shell.
     *
     * Note the payload is the back-office representation, not the trimmed
     * portal one its invoice counterpart uses. Left as it stands.
     */
    public function getEstimate(EmailLog $emailLog)
    {
        return EstimateResource::make($this->documentBehind($emailLog));
    }

    /**
     * Trade an email-log token for the offer it was issued for.
     *
     * Holding the token is the whole credential, so the guard is narrow: the
     * log must point at an offer, and the link must still be inside the
     * company's expiry window.
     */
    private function documentBehind(EmailLog $emailLog): Estimate
    {
        $document = $emailLog->mailable;

        if (! $document instanceof Estimate) {
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
    private function recordReading(Estimate $estimate): void
    {
        if (ManagedMode::readOnly()) {
            return;
        }

        $unread = [Estimate::STATUS_SENT, Estimate::STATUS_DRAFT];

        if (! in_array($estimate->status, $unread)) {
            return;
        }

        $estimate->update(['status' => Estimate::STATUS_VIEWED]);

        $wanted = CompanySetting::getSetting('notify_estimate_viewed', $estimate->company_id);

        if ($wanted != 'YES') {
            return;
        }

        $payload = [
            'estimate' => Estimate::findOrFail($estimate->id)->toArray(),
            'user' => Customer::find($estimate->customer_id)->toArray(),
        ];

        $mailbox = CompanySetting::getSetting('notification_email', $estimate->company_id);

        Mail::to($mailbox)->send(new EstimateViewedMail($payload));
    }
}
