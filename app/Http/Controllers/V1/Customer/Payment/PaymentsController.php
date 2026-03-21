<?php

namespace App\Http\Controllers\V1\Customer\Payment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\PaymentResource;
use App\Models\Company;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $payments = Payment::with(['customer', 'invoice', 'paymentMethod', 'creator'])
            ->whereCustomer(Auth::guard('customer')->id())
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->applyFilters($request->only([
                'payment_number',
                'payment_method_id',
                'orderByField',
                'orderBy',
            ]))
            ->select('payments.*', 'invoices.invoice_number')
            ->latest()
            ->paginateData($limit);

        return PaymentResource::collection($payments)
            ->additional(['meta' => [
                'paymentTotalCount' => Payment::whereCustomer(Auth::guard('customer')->id())->count(),
            ]]);
    }

    /**
     * Display the specified resource.
     *
     * @param  Payment  $payment
     * @return Response
     */
    public function show(Company $company, $id)
    {
        $payment = $company->payments()
            ->whereCustomer(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $payment) {
            return response()->json(['error' => 'payment_not_found'], 404);
        }

        return new PaymentResource($payment);
    }
}
