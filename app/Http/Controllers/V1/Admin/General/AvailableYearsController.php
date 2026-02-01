<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvailableYearsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $driver = DB::connection()->getDriverName();
        
        $invoiceDateCol = 'invoice_date';
        $paymentDateCol = 'payment_date';
        $expenseDateCol = 'expense_date';

        if ($driver === 'sqlite') {
            $invoiceYearSql = "strftime('%Y', $invoiceDateCol)";
            $paymentYearSql = "strftime('%Y', $paymentDateCol)";
            $expenseYearSql = "strftime('%Y', $expenseDateCol)";
        } elseif ($driver === 'pgsql') {
            $invoiceYearSql = "EXTRACT(YEAR FROM $invoiceDateCol)";
            $paymentYearSql = "EXTRACT(YEAR FROM $paymentDateCol)";
            $expenseYearSql = "EXTRACT(YEAR FROM $expenseDateCol)";
        } else {
            // MySQL, MariaDB, SQL Server
            $invoiceYearSql = "YEAR($invoiceDateCol)";
            $paymentYearSql = "YEAR($paymentDateCol)";
            $expenseYearSql = "YEAR($expenseDateCol)";
        }

        $invoiceYears = Invoice::whereCompany()
            ->select(DB::raw("$invoiceYearSql as year"))
            ->distinct()
            ->pluck('year')
            ->toArray();

        $paymentYears = Payment::whereCompany()
            ->select(DB::raw("$paymentYearSql as year"))
            ->distinct()
            ->pluck('year')
            ->toArray();

        $expenseYears = Expense::whereCompany()
            ->select(DB::raw("$expenseYearSql as year"))
            ->distinct()
            ->pluck('year')
            ->toArray();

        $years = array_unique(array_merge($invoiceYears, $paymentYears, $expenseYears));
        
        // Always include current year
        $years[] = date('Y');
        
        $years = array_unique($years);
        rsort($years);

        return response()->json([
            'years' => array_values($years),
        ]);
    }
}
