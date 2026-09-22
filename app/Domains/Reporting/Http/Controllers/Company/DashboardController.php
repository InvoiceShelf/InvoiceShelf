<?php

namespace App\Domains\Reporting\Http\Controllers\Company;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Reporting\Http\Requests\DashboardRequest;
use App\Domains\Reporting\Queries\CashflowQuery;
use App\Domains\Reporting\Queries\ReceivablesAgingQuery;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Silber\Bouncer\BouncerFacade;

/**
 * The company overview: the money series over a period, the headline counters
 * and the two "latest activity" lists.
 *
 * The period is the company's fiscal year (see ReportingPeriod::fiscalYear),
 * the one before it with `previous_year`, or the `from_date`/`to_date` range
 * the request names.
 */
class DashboardController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function __invoke(DashboardRequest $request, ReceivablesAgingQuery $receivables, CashflowQuery $cashflowQuery)
    {
        $companyId = $request->header('company');

        $this->authorize('view dashboard', Company::find($companyId));

        // The money chart: the fiscal year by default, or the range asked for
        $period = $request->reportingPeriod(CompanySetting::getSetting('fiscal_year', $companyId));
        $cashflow = $cashflowQuery->series($period);

        $chartData = [
            'months' => $cashflow['labels'],
            'invoice_totals' => $cashflow['invoices'],
            'expense_totals' => $cashflow['expenses'],
            'receipt_totals' => $cashflow['receipts'],
            'net_income_totals' => $cashflow['net'],
        ];

        $customerCount = Customer::query()->whereCompany()->count();

        // "How many invoices did we issue" counts issued documents, so the
        // reversals are left out. The money figures above deliberately keep
        // them: a credit note's negated total is exactly what nets a sale back
        // out. The outstanding sum below keeps them too, which is a quirk
        // rather than a decision — a credit note's due amount is always zero,
        // so it adds nothing, and the sum has always been taken over the lot.
        $invoiceCount = Invoice::query()
            ->whereCompany()
            ->where('type', Invoice::TYPE_INVOICE)
            ->count();

        $estimateCount = Estimate::query()->whereCompany()->count();

        $amountDue = Invoice::query()
            ->whereCompany()
            ->sum('base_due_amount');

        // Raw models rather than InvoiceResource: each loaded relation is
        // serialized with the full $appends set, so a column-limited
        // creditNotes load blew up inside the date accessors (the children
        // arrive without company_id) and loading them whole would run those
        // appends per credit note for nothing. Neither list needs the relation
        // anyway — credited_status is a resource-level field, and a fully
        // credited invoice has no due amount left, so it never reaches here.
        $recentDueInvoices = Invoice::with('customer')
            ->whereCompany()->where('base_due_amount', '>', 0)
            ->take(5)
            ->latest()
            ->get();

        $recentEstimates = Estimate::with('customer')
            ->whereCompany()
            ->take(5)
            ->latest()
            ->get();

        // Both lists are gated on the viewer's own document rights and come
        // back empty — never absent — when those are missing. The counters and
        // the money figures are not gated at all: holding the dashboard
        // ability is enough to see company revenue.
        return response()->json([
            'total_amount_due' => $amountDue,
            'receivables' => $receivables->summary($companyId, Carbon::now()),
            'total_customer_count' => $customerCount,
            'total_invoice_count' => $invoiceCount,
            'total_estimate_count' => $estimateCount,

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recentDueInvoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recentEstimates : [],

            'chart_data' => $chartData,

            'total_sales' => $cashflow['total_sales'],
            'total_receipts' => $cashflow['total_receipts'],
            'total_expenses' => $cashflow['total_expenses'],
            'total_net_income' => $cashflow['total_net_income'],
            'period' => $cashflow['period'],
        ]);
    }
}
