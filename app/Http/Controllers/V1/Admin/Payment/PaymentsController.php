<?php

namespace App\Http\Controllers\V1\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePaymentsRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PaymentRequest $request)
    {
        $this->authorize('create', Payment::class);

        $payment = Payment::createPayment($request);

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

        $payment = $payment->updatePayment($request);

        return new PaymentResource($payment);
    }

    public function delete(DeletePaymentsRequest $request)
    {
        $this->authorize('delete multiple payments');

        Payment::deletePayments($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
