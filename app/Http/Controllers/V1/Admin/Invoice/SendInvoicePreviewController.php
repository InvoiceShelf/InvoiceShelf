<?php

namespace InvoiceShelf\Http\Controllers\V1\Admin\Invoice;

use Illuminate\Mail\Markdown;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Http\Requests\SendInvoiceRequest;
use InvoiceShelf\Models\Invoice;

class SendInvoicePreviewController extends Controller
{
    /**
     * Mail a specific invoice to the corresponding customer's email address.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $invoice->sendInvoiceData($request->all());
        $data['url'] = $invoice->invoicePdfUrl;

        return $markdown->render('emails.send.invoice', ['data' => $data]);
    }
}
