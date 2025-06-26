<?php

namespace App\Http\Controllers\V1\Customer\General;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $user = Auth::guard('customer')->user();

        // Determine if active filter is enabled
        $activeOnly = $request->boolean('active_only', false);

        // Build amount due query with optional active filter
        $amountDueQuery = Invoice::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT');

        if ($activeOnly) {
            $amountDueQuery->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_VIEWED,
            ])->whereIn('paid_status', [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_PARTIALLY_PAID,
            ]);
        }

        $amountDue = $amountDueQuery->sum('due_amount');

        // Build invoice count query with optional active filter
        $invoiceCountQuery = Invoice::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT');

        if ($activeOnly) {
            $invoiceCountQuery->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_VIEWED,
            ])->whereIn('paid_status', [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_PARTIALLY_PAID,
            ]);
        }

        $invoiceCount = $invoiceCountQuery->count();

        // Build estimates count query with optional active filter
        $estimatesCountQuery = Estimate::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT');

        if ($activeOnly) {
            $estimatesCountQuery->whereIn('status', [
                Estimate::STATUS_SENT,
                Estimate::STATUS_VIEWED,
            ]);
        }

        $estimatesCount = $estimatesCountQuery->count();

        // Payment count (no active filter needed as payments are always considered active)
        $paymentCount = Payment::whereCustomer($user->id)->count();

        // Build recent invoices query with optional active filter
        $recentInvoicesQuery = Invoice::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT');

        if ($activeOnly) {
            $recentInvoicesQuery->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_VIEWED,
            ])->whereIn('paid_status', [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_PARTIALLY_PAID,
            ]);
        }

        $recentInvoices = $recentInvoicesQuery->take(5)->latest()->get();

        // Build recent estimates query with optional active filter
        $recentEstimatesQuery = Estimate::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT');

        if ($activeOnly) {
            $recentEstimatesQuery->whereIn('status', [
                Estimate::STATUS_SENT,
                Estimate::STATUS_VIEWED,
            ]);
        }

        $recentEstimates = $recentEstimatesQuery->take(5)->latest()->get();

        return response()->json([
            'due_amount' => $amountDue,
            'recentInvoices' => $recentInvoices,
            'recentEstimates' => $recentEstimates,
            'invoice_count' => $invoiceCount,
            'estimate_count' => $estimatesCount,
            'payment_count' => $paymentCount,

            // Include filter state in response for debugging
            'active_filter_applied' => $activeOnly,
        ]);
    }
}
