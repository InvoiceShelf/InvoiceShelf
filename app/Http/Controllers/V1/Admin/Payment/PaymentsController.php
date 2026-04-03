<?php

namespace App\Http\Controllers\V1\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePaymentsRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\SendPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\Markdown;

class PaymentsController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $payments = Payment::whereCompany()
            ->join('customers', 'customers.id', '=', 'payments.customer_id')
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->applyFilters($request->all())
            ->select('payments.*', 'customers.name', 'invoices.invoice_number', 'payment_methods.name as payment_mode')
            ->latest()
            ->paginateData($limit);

        return PaymentResource::collection($payments)
            ->additional(['meta' => [
                'payment_total_count' => Payment::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(PaymentRequest $request)
    {
        $this->authorize('create', Payment::class);

        $payment = $this->paymentService->create($request);

        return new PaymentResource($payment);
    }

    public function show(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);

        return new PaymentResource($payment);
    }

    public function update(PaymentRequest $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        $payment = $this->paymentService->update($payment, $request);

        return new PaymentResource($payment);
    }

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

    public function send(SendPaymentRequest $request, Payment $payment)
    {
        $this->authorize('send payment', $payment);

        $response = $this->paymentService->send($payment, $request->all());

        return response()->json($response);
    }

    public function sendPreview(Request $request, Payment $payment)
    {
        $this->authorize('send payment', $payment);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->paymentService->sendPaymentData($payment, $request->all());
        $data['url'] = $payment->paymentPdfUrl;

        return $markdown->render('emails.send.payment', ['data' => $data]);
    }
}
