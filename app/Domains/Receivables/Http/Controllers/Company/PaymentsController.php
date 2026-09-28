<?php

namespace App\Domains\Receivables\Http\Controllers\Company;

use App\Domains\Receivables\Application\PaymentAllocationService;
use App\Domains\Receivables\Application\PaymentService;
use App\Domains\Receivables\Http\Requests\DeletePaymentsRequest;
use App\Domains\Receivables\Http\Requests\PaymentRequest;
use App\Domains\Receivables\Http\Requests\ReplacePaymentAllocationsRequest;
use App\Domains\Receivables\Http\Requests\SendPaymentRequest;
use App\Domains\Receivables\Http\Resources\PaymentResource;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

/**
 * Company-scoped payment endpoints: the listing, the write surface, the
 * standalone allocation replace, bulk removal, and the receipt mailer.
 *
 * Money is handled by the service layer; the controller authorizes, hands the
 * validated payload over, and renders the resource.
 */
class PaymentsController extends Controller
{
    public function __construct(
        private readonly PaymentAllocationService $paymentAllocationService,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Paginated payments of the active company, newest first.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        // Customer and method are joined rather than eager loaded: rows are
        // searched and ordered by the customer name, and each row carries the
        // method label as payment_mode. The company scope has to precede the
        // filters, because the payment_id filter widens the query with an OR.
        $payments = Payment::with(['allocations.invoice'])
            ->whereCompany()
            ->join('customers', 'customers.id', '=', 'customer_payments.customer_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'customer_payments.payment_method_id')
            ->applyFilters($request->all())
            ->select('customer_payments.*', 'customers.name', 'payment_methods.name as payment_mode')
            ->latest()
            ->paginateData($request->input('limit', 10));

        return PaymentResource::collection($payments)
            ->additional([
                'meta' => [
                    'payment_total_count' => Payment::whereCompany()->count(),
                ],
            ]);
    }

    /**
     * Record a received amount, with the invoices it settles, if any.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(PaymentRequest $request)
    {
        $this->authorize('create', Payment::class);

        $payment = $this->paymentService->create(
            attributes: $request->getPaymentPayload(),
            allocations: $request->validated('allocations') ?? [],
            customFields: $this->customFields($request),
        );

        return new PaymentResource($payment);
    }

    /**
     * One payment with the invoices its rows point at.
     *
     * @return JsonResponse
     */
    public function show(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);

        return new PaymentResource($payment->load(['allocations.invoice']));
    }

    /**
     * Overwrite a payment. Allocations are only re-cut when the payload carries
     * an allocations key; leaving it out keeps the rows already on record.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function update(PaymentRequest $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        $payment = $this->paymentService->update(
            payment: $payment,
            attributes: $request->getPaymentPayload(),
            replaceAllocations: $request->exists('allocations'),
            allocations: $request->validated('allocations') ?? [],
            customFields: $this->customFields($request),
        );

        return new PaymentResource($payment);
    }

    /**
     * Re-cut the whole allocation set of one payment; an empty list releases
     * every invoice it was covering.
     *
     * @return JsonResponse
     */
    public function replaceAllocations(ReplacePaymentAllocationsRequest $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        abort_unless((int) $payment->company_id === (int) $request->header('company'), 404);

        $payment = $this->paymentAllocationService->replace($payment, $request->validated('allocations'));

        return new PaymentResource($payment->load(['allocations.invoice']));
    }

    /**
     * Drop several payments at once. Ids outside the active company are quietly
     * dropped from the set before the service deallocates and deletes them.
     *
     * @return JsonResponse
     */
    public function delete(DeletePaymentsRequest $request)
    {
        $this->authorize('delete multiple payments');

        $ids = Payment::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        $this->paymentService->delete($ids);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Mail the receipt, using the company's own mail configuration.
     *
     * @return JsonResponse
     */
    public function send(SendPaymentRequest $request, Payment $payment)
    {
        $this->authorize('send payment', $payment);

        $response = $this->paymentService->send($payment, $request->all());

        return response()->json($response);
    }

    /**
     * Render the receipt mail body the composer is currently holding, so the
     * SPA can show it before anything is sent.
     */
    public function sendPreview(Request $request, Payment $payment)
    {
        $this->authorize('send payment', $payment);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->paymentService->sendPaymentData($payment, $request->all());
        $data['url'] = $payment->paymentPdfUrl;

        return $markdown->render('emails.send.payment', ['data' => $data]);
    }

    /**
     * Custom field values are optional and arrive untyped, so anything that is
     * not a list of rows is treated as "none supplied".
     */
    private function customFields(PaymentRequest $request): ?iterable
    {
        $customFields = $request->input('customFields');

        return is_iterable($customFields) ? $customFields : null;
    }
}
