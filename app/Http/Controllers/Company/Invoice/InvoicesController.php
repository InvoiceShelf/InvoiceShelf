<?php

namespace App\Http\Controllers\Company\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Http\Requests\CreateCreditNoteRequest;
use App\Http\Requests\DeleteInvoiceRequest;
use App\Http\Requests\SendInvoiceRequest;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\EstimateResource;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\Document\CreditNoteService;
use App\Services\Document\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Validation\ValidationException;

class InvoicesController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly CreditNoteService $creditNoteService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $limit = $request->input('limit', 10);

        // creditNotes drives the "cancelled" badge on every row, so it is
        // eager-loaded (two columns) rather than probed per row.
        $invoices = Invoice::whereCompany()
            ->applyFilters($request->all())
            ->with(['customer', 'creditNotes:id,related_invoice_id,invoice_number,total'])
            ->latest()
            ->paginateData($limit);

        return InvoiceResource::collection($invoices)
            ->additional(['meta' => [
                'invoice_total_count' => Invoice::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Requests\InvoicesRequest $request)
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create($request);

        if ($request->has('invoiceSend')) {
            $this->invoiceService->send($invoice, $request->only(['subject', 'body']));
        }

        GenerateInvoicePdfJob::dispatch($invoice);

        return new InvoiceResource($invoice);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        if ($invoice->isCreditNote()) {
            return new CreditNoteResource($invoice->load('relatedInvoice'));
        }

        // Feeds the credit-note banner on the detail page: how much of the
        // invoice has been credited, and how much of each line, so the partial
        // credit form can offer the remaining quantities.
        return new InvoiceResource($invoice->load([
            'creditNotes:id,related_invoice_id,invoice_number,total',
            'creditNotes.items:id,invoice_id,source_invoice_item_id,quantity',
        ]));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function update(Requests\InvoicesRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice = $this->invoiceService->update($invoice, $request);

        GenerateInvoicePdfJob::dispatch($invoice, true);

        return new InvoiceResource($invoice);
    }

    /**
     * delete the specified resources in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteInvoiceRequest $request)
    {
        $this->authorize('delete multiple invoices');

        $ids = Invoice::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        $this->invoiceService->delete($ids);

        return response()->json([
            'success' => true,
        ]);
    }

    public function send(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $this->invoiceService->send($invoice, $request->all());

        return response()->json([
            'success' => true,
        ]);
    }

    public function sendPreview(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->invoiceService->sendInvoiceData($invoice, $request->all());
        $data['url'] = $invoice->invoicePdfUrl;

        // Preview the template that will actually be sent: a credit note goes
        // out through SendCreditNoteMail, so it must preview as one.
        $view = $invoice->isCreditNote() ? 'emails.send.credit-note' : 'emails.send.invoice';

        return $markdown->render($view, ['data' => $data]);
    }

    public function clone(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $this->authorize('create', Invoice::class);

        // Cloning a credit note would mint a positive invoice out of a reversal
        // document. Domain rule violation (422), not an authorization failure.
        if ($invoice->isCreditNote()) {
            throw ValidationException::withMessages([
                'invoice' => ['a_credit_note_cannot_be_cloned'],
            ]);
        }

        $newInvoice = $this->invoiceService->clone($invoice);

        return new InvoiceResource($newInvoice);
    }

    public function convertToEstimate(Request $request, Invoice $invoice)
    {
        // Authorize access to the source invoice (tenant isolation) in addition
        // to the ability to create an estimate.
        $this->authorize('view', $invoice);
        $this->authorize('create', Estimate::class);

        // Same reason as clone(): the conversion copies the amounts unnegated,
        // so a credit note would become a positive estimate.
        if ($invoice->isCreditNote()) {
            throw ValidationException::withMessages([
                'invoice' => ['a_credit_note_cannot_be_converted_to_an_estimate'],
            ]);
        }

        $estimate = $this->invoiceService->convertToEstimate($invoice);

        return new EstimateResource($estimate);
    }

    public function createCreditNote(CreateCreditNoteRequest $request, Invoice $invoice)
    {
        $this->authorize('create credit note', $invoice);

        // A credit note can only reverse a real invoice, never another credit
        // note. This is a domain rule (422), not an authorization failure (403).
        if ($invoice->isCreditNote()) {
            throw ValidationException::withMessages([
                'invoice' => ['a_credit_note_cannot_be_created_from_a_credit_note'],
            ]);
        }

        // A draft was never issued, so there is nothing to reverse: edit or
        // delete it instead.
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'invoice' => ['a_draft_invoice_cannot_be_credited'],
            ]);
        }

        // How much of the invoice is still creditable, and whether the credit
        // fits inside its unpaid balance, is decided by the service under a row
        // lock. Guarding it here would race.
        $creditNote = $this->creditNoteService->create(
            $invoice,
            $request->input('items', []),
            $request->input('reason')
        );

        GenerateInvoicePdfJob::dispatch($creditNote);

        // The original's own PDF changed too: its balance moved and it now
        // carries the cancellation banner, so the stored file is replaced.
        GenerateInvoicePdfJob::dispatch($invoice->fresh(), true);

        return (new CreditNoteResource($creditNote))
            ->response()
            ->setStatusCode(201);
    }

    public function changeStatus(Request $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $this->invoiceService->changeStatus($invoice, $request->status);

        return response()->json([
            'success' => true,
        ]);
    }
}
