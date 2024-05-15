<?php

namespace InvoiceShelf\Http\Controllers\V1\Installer\General;

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
        $user = Auth::guard('installer')->user();

        $amountDue = Invoice::whereInstaller($user->id)
            ->where('status', '<>', 'DRAFT')
            ->sum('due_amount');
        $invoiceCount = Invoice::whereInstaller($user->id)
            ->where('status', '<>', 'DRAFT')
            ->count();
        $estimatesCount = Estimate::whereInstaller($user->id)
            ->where('status', '<>', 'DRAFT')
            ->count();
        $paymentCount = Payment::whereInstaller($user->id)
            ->count();

        return response()->json([
            'due_amount' => $amountDue,
            'recentInvoices' => Invoice::whereInstaller($user->id)->where('status', '<>', 'DRAFT')->take(5)->latest()->get(),
            'recentEstimates' => Estimate::whereInstaller($user->id)->where('status', '<>', 'DRAFT')->take(5)->latest()->get(),
            'invoice_count' => $invoiceCount,
            'estimate_count' => $estimatesCount,
            'payment_count' => $paymentCount,
        ]);
    }
}
