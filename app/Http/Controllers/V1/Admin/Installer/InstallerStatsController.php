<?php

namespace InvoiceShelf\Http\Controllers\V1\Admin\Installer;

use Carbon\Carbon;
use Illuminate\Http\Request;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Http\Resources\InstallerResource;
use InvoiceShelf\Models\CompanySetting;
use InvoiceShelf\Models\Installer;
use InvoiceShelf\Models\Expense;
use InvoiceShelf\Models\Invoice;
use InvoiceShelf\Models\Payment;

class InstallerStatsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Installer $installer)
    {
        $this->authorize('view', $installer);

        $i = 0;
        $months = [];
        $invoiceTotals = [];
        $expenseTotals = [];
        $receiptTotals = [];
        $netProfits = [];
        $monthCounter = 0;
        $fiscalYear = CompanySetting::getSetting('fiscal_year', $request->header('company'));
        $startDate = Carbon::now();
        $start = Carbon::now();
        $end = Carbon::now();
        $terms = explode('-', $fiscalYear);

        if ($terms[0] <= $start->month) {
            $startDate->month($terms[0])->startOfMonth();
            $start->month($terms[0])->startOfMonth();
            $end->month($terms[0])->endOfMonth();
        } else {
            $startDate->subYear()->month($terms[0])->startOfMonth();
            $start->subYear()->month($terms[0])->startOfMonth();
            $end->subYear()->month($terms[0])->endOfMonth();
        }

        if ($request->has('previous_year')) {
            $startDate->subYear()->startOfMonth();
            $start->subYear()->startOfMonth();
            $end->subYear()->endOfMonth();
        }
        // while ($monthCounter < 12) {
        //     array_push(
        //         $invoiceTotals,
        //         Invoice::whereBetween(
        //             'invoice_date',
        //             [$start->format('Y-m-d'), $end->format('Y-m-d')]
        //         )
        //             ->whereCompany()
        //             ->whereInstaller($installer->id)
        //             ->sum('total') ?? 0
        //     );
        //     array_push(
        //         $expenseTotals,
        //         Expense::whereBetween(
        //             'expense_date',
        //             [$start->format('Y-m-d'), $end->format('Y-m-d')]
        //         )
        //             ->whereCompany()
        //             ->whereUser($installer->id)
        //             ->sum('amount') ?? 0
        //     );
        //     array_push(
        //         $receiptTotals,
        //         Payment::whereBetween(
        //             'payment_date',
        //             [$start->format('Y-m-d'), $end->format('Y-m-d')]
        //         )
        //             ->whereCompany()
        //             ->whereInstaller($installer->id)
        //             ->sum('amount') ?? 0
        //     );
        //     array_push(
        //         $netProfits,
        //         ($receiptTotals[$i] - $expenseTotals[$i])
        //     );
        //     $i++;
        //     array_push($months, $start->format('M'));
        //     $monthCounter++;
        //     $end->startOfMonth();
        //     $start->addMonth()->startOfMonth();
        //     $end->addMonth()->endOfMonth();
        // }

        $start->subMonth()->endOfMonth();

        $salesTotal = Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->whereInstaller($installer->id)
            ->sum('total');
        // $totalReceipts = Payment::whereBetween(
        //     'payment_date',
        //     [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        // )
        //     ->whereCompany()
        //     ->whereInstaller($installer->id)
        //     ->sum('amount');
        $totalExpenses = Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->whereUser($installer->id)
            ->sum('amount');
        //$netProfit = (int) $totalReceipts - (int) $totalExpenses;

        $chartData = [
            'months' => $months,
            'invoiceTotals' => $invoiceTotals,
            'expenseTotals' => $expenseTotals,
            'receiptTotals' => $receiptTotals,
            //'netProfit' => $netProfit,
            'netProfits' => $netProfits,
            'salesTotal' => $salesTotal,
            //'totalReceipts' => $totalReceipts,
            'totalExpenses' => $totalExpenses,
        ];

        $Installer = Installer::find($installer->id);

        return (new InstallerResource($installer))
            ->additional(['meta' => [
                'chartData' => $chartData,
            ]]);
    }
}
