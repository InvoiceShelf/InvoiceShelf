<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Sales\Events\InvoiceViewed;
use App\Domains\Sales\Http\Resources\CustomerPortal\InvoiceResource;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use App\Platform\Mail\Models\EmailLog;
use Illuminate\Http\Request;

class InvoicePdfController extends Controller
{
    /**
     * Answer an emailed link: the rendered document when the caller asks for
     * the file, otherwise the portal shell that frames it. Either way the
     * visit counts as the customer having read the bill.
     */
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $invoice = $this->documentBehind($emailLog);

        $this->recordReading($invoice);

        if ($request->has('pdf')) {
            return $invoice->getGeneratedPDFOrStream('invoice');
        }

        $issuer = $invoice->company_id;

        return view('app')->with([
            'customer_logo' => get_company_setting('customer_portal_logo', $issuer),
            'current_theme' => get_company_setting('customer_portal_theme', $issuer),
        ]);
    }

    /**
     * Serve the same document as JSON for the viewer shell, in the trimmed
     * portal shape.
     */
    public function getInvoice(EmailLog $emailLog)
    {
        return InvoiceResource::make($this->documentBehind($emailLog));
    }

    /**
     * Trade an email-log token for the document it was issued for.
     *
     * Holding the token is the whole credential, so the guard is narrow. The
     * log must point at a billing document (a token minted for some other
     * kind of mail must not disclose one, however the ids line up), and the
     * link must still be inside the company's expiry window.
     */
    private function documentBehind(EmailLog $emailLog): Invoice
    {
        $document = $emailLog->mailable;

        if (! $document instanceof Invoice) {
            abort(404);
        }

        if ($emailLog->isExpired()) {
            abort(403, 'Link Expired.');
        }

        return $document;
    }

    /**
     * Promote a document that is still awaiting a reader, and let the issuer
     * know.
     */
    private function recordReading(Invoice $invoice): void
    {
        $unread = [Invoice::STATUS_SENT, Invoice::STATUS_DRAFT];

        if (! in_array($invoice->status, $unread)) {
            return;
        }

        $invoice->update([
            'status' => Invoice::STATUS_VIEWED,
            'viewed' => true,
        ]);

        InvoiceViewed::dispatch((int) $invoice->id, (int) $invoice->company_id);
    }
}
