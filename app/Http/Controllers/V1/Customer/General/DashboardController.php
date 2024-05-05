<?php

namespace InvoiceShelf\Http\Controllers\V1\Customer\General;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Models\Estimate;
use InvoiceShelf\Models\Invoice;
use InvoiceShelf\Models\Payment;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $user = Auth::guard('customer')->user();

        $amountDue = Invoice::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT')
            ->sum('due_amount');
        $invoiceCount = Invoice::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT')
            ->count();
        $estimatesCount = Estimate::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT')
            ->count();
        $paymentCount = Payment::whereCustomer($user->id)
            ->count();

        return response()->json([
            'due_amount' => $amountDue,
            'recentInvoices' => Invoice::whereCustomer($user->id)->where('status', '<>', 'DRAFT')->take(5)->latest()->get(),
            'recentEstimates' => Estimate::whereCustomer($user->id)->where('status', '<>', 'DRAFT')->take(5)->latest()->get(),
            'invoice_count' => $invoiceCount,
            'estimate_count' => $estimatesCount,
            'payment_count' => $paymentCount,
        ]);
    }
}
