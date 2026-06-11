<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\InvoiceResource as CustomerInvoiceResource;
use App\Mail\InvoiceViewedMail;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoicePdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        // Resolve the document through the morph relation and enforce the
        // expected type — a token issued for another mailable type (or whose
        // numeric id collides) must not disclose an invoice.
        $invoice = $emailLog->mailable;
        abort_unless($invoice instanceof Invoice, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        if ($invoice->status == Invoice::STATUS_SENT || $invoice->status == Invoice::STATUS_DRAFT) {
            $invoice->status = Invoice::STATUS_VIEWED;
            $invoice->viewed = true;
            $invoice->save();
            $notifyInvoiceViewed = CompanySetting::getSetting(
                'notify_invoice_viewed',
                $invoice->company_id
            );

            if ($notifyInvoiceViewed == 'YES') {
                $data['invoice'] = Invoice::findOrFail($invoice->id)->toArray();
                $data['user'] = Customer::find($invoice->customer_id)->toArray();
                $notificationEmail = CompanySetting::getSetting(
                    'notification_email',
                    $invoice->company_id
                );

                \Mail::to($notificationEmail)->send(new InvoiceViewedMail($data));
            }
        }

        if ($request->has('pdf')) {
            return $invoice->getGeneratedPDFOrStream('invoice');
        }

        return view('app')->with([
            'customer_logo' => get_company_setting('customer_portal_logo', $invoice->company_id),
            'current_theme' => get_company_setting('customer_portal_theme', $invoice->company_id),
        ]);
    }

    public function getInvoice(EmailLog $emailLog)
    {
        $invoice = $emailLog->mailable;
        abort_unless($invoice instanceof Invoice, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        return new CustomerInvoiceResource($invoice);
    }
}
