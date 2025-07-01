<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DashboardExportService
{
    protected $pdfService;

    public function __construct(PDFService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    public function generate(Request $request)
    {
        $format = $request->input('format');
        $sections = $request->input('sections');
        $data = $this->collectData($request, $sections);

        switch ($format) {
            case 'pdf':
                return $this->createPdfResponse($data);
            case 'xlsx':
                return $this->createXlsxResponse($data, $request);
            case 'csv':
                return $this->createCsvResponse($data);
            default:
                // Should not happen due to validation
                abort(400, 'Invalid export format');
        }
    }

    public function generateSnapshot(Request $request)
    {
        $chartImages = $request->input('chartImages');
        $tableData = $request->input('tableData');
        $selectedSections = $request->input('selectedSections');
        $dashboardData = $request->input('dashboardData');

        // Add company information
        $company = Company::find($request->header('company'));

        $data = [
            'company' => $company,
            'chartImages' => $chartImages,
            'tableData' => $tableData,
            'selectedSections' => $selectedSections,
            'dashboardData' => $dashboardData,
            'generatedAt' => now()->format('F j, Y \a\t g:i A'),
        ];

        return $this->createSnapshotPdfResponse($data);
    }

    protected function collectData(Request $request, array $sections)
    {
        $data = [];
        $dashboardData = null;

        if (in_array('dashboard', $sections) || in_array('cashflow', $sections)) {
            $dashboardData = $this->getSummaryData($request);
        }

        if (in_array('dashboard', $sections)) {
            $data['dashboard'] = [
                'summary' => [
                    'total_sales' => $dashboardData['total_sales'] ?? 0,
                    'total_receipts' => $dashboardData['total_receipts'] ?? 0,
                    'total_expenses' => $dashboardData['total_expenses'] ?? 0,
                    'total_net_income' => $dashboardData['total_net_income'] ?? 0,
                ],
                'distribution' => $dashboardData['status_distribution'] ?? [],
                'outstanding' => $this->getTopOutstandingData($request),
            ];
        }

        if (in_array('cashflow', $sections)) {
            $data['cashflow'] = $dashboardData['chart_data'] ?? [];
        }

        if (in_array('invoices', $sections)) {
            $data['invoices'] = $this->getInvoicesData($request);
        }

        return $data;
    }

    private function getInvoicesData(Request $request)
    {
        $invoices = Invoice::with('customer', 'items')
            ->whereCompany()
            ->when($request->has('customer_id'), function ($query) use ($request) {
                return $query->where('customer_id', $request->customer_id);
            })
            ->when($request->has('status'), function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->has('from_date'), function ($query) use ($request) {
                return $query->where('invoice_date', '>=', $request->from_date);
            })
            ->when($request->has('to_date'), function ($query) use ($request) {
                return $query->where('invoice_date', '<=', $request->to_date);
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('invoice_number', 'like', '%'.$request->search.'%')
                        ->orWhere('notes', 'like', '%'.$request->search.'%')
                        ->orWhereHas('customer', function ($subQuery) use ($request) {
                            $subQuery->where('name', 'like', '%'.$request->search.'%');
                        });
                });
            });

        $orderByField = $request->input('orderByField', 'created_at');
        $orderBy = $request->input('orderBy', 'desc');

        $invoices = $invoices->orderBy($orderByField, $orderBy)->get();

        // Using toArray to get a consistent structure with original implementation
        return $invoices->toArray();
    }

    private function getSummaryData(Request $request)
    {
        $paid_count = Invoice::whereCompany()->where('paid_status', Invoice::STATUS_PAID)->count();
        $overdue_count = Invoice::whereCompany()->where('paid_status', Invoice::STATUS_UNPAID)
            ->where('due_date', '<', Carbon::now())
            ->count();
        $pending_count = Invoice::whereCompany()->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
            ->where('due_date', '>=', Carbon::now())
            ->count();

        $chart_data = $this->getCashFlowData($request);

        return [
            'total_sales' => $chart_data['total_sales'],
            'total_receipts' => $chart_data['total_receipts'],
            'total_expenses' => $chart_data['total_expenses'],
            'total_net_income' => $chart_data['total_net_income'],
            'status_distribution' => [
                'paid' => $paid_count,
                'overdue' => $overdue_count,
                'pending' => $pending_count,
            ],
            'chart_data' => $chart_data,
        ];
    }

    private function getTopOutstandingData(Request $request)
    {
        $type = $request->input('type', 'clients');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        $query = Invoice::where('base_due_amount', '>', 0)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereCompany();

        if ($type === 'clients') {
            $results = $query
                ->join('customers', 'invoices.customer_id', '=', 'customers.id')
                ->select('customers.name as label', DB::raw('SUM(invoices.base_due_amount) as value'))
                ->groupBy('customers.name')
                ->orderBy('value', 'desc');
        } else { // products
            $results = $query
                ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->join('items', 'invoice_items.item_id', '=', 'items.id')
                ->select('items.name as label', DB::raw('SUM(invoice_items.quantity * invoice_items.price) as value'))
                ->groupBy('items.name')
                ->orderBy('value', 'desc');
        }

        return $results->take(5)->get();
    }

    private function getCashFlowData(Request $request)
    {
        $invoice_totals = [];
        $expense_totals = [];
        $receipt_totals = [];
        $net_income_totals = [];
        $months = [];

        $fiscalYear = CompanySetting::getSetting('fiscal_year', $request->header('company'));
        $terms = explode('-', $fiscalYear);
        $companyStartMonth = intval($terms[0]);
        $start = Carbon::now()->month($companyStartMonth)->startOfMonth();

        if (Carbon::now()->month < $companyStartMonth) {
            $start->subYear();
        }

        if ($request->has('previous_year')) {
            $start->subYear();
        }

        $startDateForTotals = $start->clone();

        for ($i = 0; $i < 12; $i++) {
            $end = $start->clone()->endOfMonth();

            $invoice_totals[] = Invoice::whereBetween('invoice_date', [$start, $end])->whereCompany()->sum('base_total');
            $expense_totals[] = Expense::whereBetween('expense_date', [$start, $end])->whereCompany()->sum('base_amount');
            $receipt_totals[] = Payment::whereBetween('payment_date', [$start, $end])->whereCompany()->sum('base_amount');
            $net_income_totals[] = end($receipt_totals) - end($expense_totals);

            $months[] = $start->translatedFormat('M');
            $start->addMonth();
        }

        $endDateForTotals = $start->subMonth()->endOfMonth();

        $total_sales = Invoice::whereBetween('invoice_date', [$startDateForTotals, $endDateForTotals])->whereCompany()->sum('base_total');
        $total_receipts = Payment::whereBetween('payment_date', [$startDateForTotals, $endDateForTotals])->whereCompany()->sum('base_amount');
        $total_expenses = Expense::whereBetween('expense_date', [$startDateForTotals, $endDateForTotals])->whereCompany()->sum('base_amount');

        return [
            'months' => $months,
            'invoice_totals' => $invoice_totals,
            'expense_totals' => $expense_totals,
            'receipt_totals' => $receipt_totals,
            'net_income_totals' => $net_income_totals,
            'total_sales' => $total_sales,
            'total_receipts' => $total_receipts,
            'total_expenses' => $total_expenses,
            'total_net_income' => $total_receipts - $total_expenses,
        ];
    }

    protected function createPdfResponse(array $data)
    {
        $pdfContent = PDFService::loadView('app.pdf.dashboard_export', ['data' => $data])->output();

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dashboard-report.pdf"',
        ]);
    }

    protected function createXlsxResponse(array $data, Request $request)
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0); // Remove the default sheet

        if (isset($data['dashboard'])) {
            $this->addDashboardSheet($spreadsheet, $data['dashboard']);
        }
        if (isset($data['cashflow'])) {
            $this->addCashflowSheet($spreadsheet, $data['cashflow']);
        }
        if (isset($data['invoices'])) {
            $this->addInvoicesSheet($spreadsheet, $data['invoices']);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="dashboard-export.xlsx"',
        ]);
    }

    private function addDashboardSheet(Spreadsheet $spreadsheet, array $data)
    {
        $sheet = new Worksheet($spreadsheet, 'Dashboard Overview');
        $spreadsheet->addSheet($sheet);

        $row = 1;
        $sheet->setCellValue('A'.$row, 'Dashboard Summary');
        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;

        foreach ($data['summary'] as $key => $value) {
            $sheet->setCellValue('A'.$row, ucwords(str_replace('_', ' ', $key)));
            $sheet->setCellValue('B'.$row, $value);
            $row++;
        }
        $row++;

        $sheet->setCellValue('A'.$row, 'Status Distribution');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;
        foreach ($data['distribution'] as $key => $value) {
            $sheet->setCellValue('A'.$row, ucfirst($key));
            $sheet->setCellValue('B'.$row, $value);
            $row++;
        }
        $row++;

        $sheet->setCellValue('A'.$row, 'Top Outstanding Invoices');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue('A'.$row, 'Label');
        $sheet->setCellValue('B'.$row, 'Value');
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true);
        $row++;
        foreach ($data['outstanding'] as $item) {
            $sheet->setCellValue('A'.$row, $item['label']);
            $sheet->setCellValue('B'.$row, $item['value']);
            $row++;
        }
    }

    private function addCashflowSheet(Spreadsheet $spreadsheet, array $data)
    {
        $sheet = new Worksheet($spreadsheet, 'Cash Flow');
        $spreadsheet->addSheet($sheet);

        $sheet->setCellValue('A1', 'Month');
        $sheet->setCellValue('B1', 'Invoice Totals');
        $sheet->setCellValue('C1', 'Expense Totals');
        $sheet->setCellValue('D1', 'Receipt Totals');
        $sheet->setCellValue('E1', 'Net Income');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $row = 2;
        foreach ($data['months'] as $index => $month) {
            $sheet->setCellValue('A'.$row, $month);
            $sheet->setCellValue('B'.$row, $data['invoice_totals'][$index] ?? 0);
            $sheet->setCellValue('C'.$row, $data['expense_totals'][$index] ?? 0);
            $sheet->setCellValue('D'.$row, $data['receipt_totals'][$index] ?? 0);
            $sheet->setCellValue('E'.$row, $data['net_income_totals'][$index] ?? 0);
            $row++;
        }
    }

    private function addInvoicesSheet(Spreadsheet $spreadsheet, array $data)
    {
        $sheet = new Worksheet($spreadsheet, 'Recent Invoices');
        $spreadsheet->addSheet($sheet);

        $headers = ['Invoice #', 'Status', 'Date', 'Customer', 'Contact', 'Total', 'Amount Due'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $row = 2;
        foreach ($data as $invoice) {
            $sheet->setCellValue('A'.$row, $invoice['invoice_number']);
            $sheet->setCellValue('B'.$row, $invoice['status']);
            $sheet->setCellValue('C'.$row, $invoice['invoice_date']);
            $sheet->setCellValue('D'.$row, $invoice['customer']['name']);
            $sheet->setCellValue('E'.$row, $invoice['customer']['contact_name']);
            $sheet->setCellValue('F'.$row, $invoice['total']);
            $sheet->setCellValue('G'.$row, $invoice['due_amount']);
            $row++;
        }
    }

    protected function createCsvResponse(array $data)
    {
        if (! isset($data['invoices'])) {
            return response()->json([
                'message' => 'The CSV export format is only available for the Recent Invoices section. Please select it to proceed.',
            ], 400);
        }

        $invoices = $data['invoices'];
        $headers = ['Invoice #', 'Status', 'Date', 'Customer', 'Contact', 'Total', 'Amount Due'];

        return response()->stream(function () use ($invoices, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice['invoice_number'],
                    $invoice['status'],
                    $invoice['invoice_date'],
                    $invoice['customer']['name'],
                    $invoice['customer']['contact_name'],
                    $invoice['total'],
                    $invoice['due_amount'],
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="invoices-export.csv"',
        ]);
    }

    protected function createSnapshotPdfResponse(array $data)
    {
        $pdfContent = $this->pdfService->loadView('app.pdf.dashboard_snapshot', $data)->output();

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dashboard-snapshot-'.now()->format('Y-m-d-H-i-s').'.pdf"',
        ]);
    }
}
