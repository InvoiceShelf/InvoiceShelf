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
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
// Chart-related imports for native Excel charts
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
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

    /**
     * Format numeric values consistently across all export formats
     *
     * This method ensures that all numeric values are rounded to a consistent
     * number of decimal places across PDF, XLSX, and CSV exports, preventing
     * discrepancies where PDF shows rounded values while XLSX/CSV show
     * full precision database values.
     *
     * Monetary values in the database are stored as integers (cents), so this
     * method converts them to decimal currency units by dividing by 100.
     *
     * @param  mixed  $value  The value to format
     * @param  int  $decimals  Number of decimal places (default: 2)
     * @param  bool  $isMonetary  Whether the value is a monetary amount stored as cents (default: true)
     * @return float|mixed Returns rounded float for numeric values, original value otherwise
     */
    private function formatNumericValue($value, $decimals = 2, $isMonetary = true)
    {
        if (is_numeric($value)) {
            $floatValue = (float) $value;

            // Convert from cents to currency units if it's a monetary value
            if ($isMonetary && $floatValue > 0) {
                $floatValue = $floatValue / 100;
            }

            return round($floatValue, $decimals);
        }

        return $value;
    }

    /**
     * Recursively format all numeric values in an array
     *
     * This method traverses an array recursively and applies consistent
     * numeric formatting to all numeric values, ensuring data consistency
     * across all export formats.
     *
     * @param  array  $data  The array containing data to format
     * @param  int  $decimals  Number of decimal places (default: 2)
     * @param  bool|null  $forceMonetary  Force all values to be treated as monetary (null = auto-detect)
     * @return array The array with all numeric values consistently formatted
     */
    private function formatArrayNumericValues($data, $decimals = 2, $forceMonetary = null)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->formatArrayNumericValues($value, $decimals, $forceMonetary);
            } elseif (is_numeric($value)) {
                // Determine if this is a monetary field
                if ($forceMonetary !== null) {
                    $isMonetary = $forceMonetary;
                } else {
                    $isMonetary = $this->isMonetaryField($key);
                }
                $data[$key] = $this->formatNumericValue($value, $decimals, $isMonetary);
            }
        }

        return $data;
    }

    /**
     * Determine if a field contains monetary values (stored as cents)
     *
     * @param  string  $fieldName  The name of the field
     * @return bool True if the field contains monetary values
     */
    private function isMonetaryField($fieldName)
    {
        // Fields that contain monetary values (stored as cents in database)
        $monetaryFields = [
            'total', 'base_total', 'amount', 'base_amount', 'due_amount', 'base_due_amount',
            'sub_total', 'base_sub_total', 'tax', 'base_tax', 'price', 'base_price',
            'discount_val', 'base_discount_val', 'value', 'total_sales', 'total_receipts',
            'total_expenses', 'total_net_income',
        ];

        // Array fields that contain monetary values
        $monetaryArrays = [
            'invoice_totals', 'expense_totals', 'receipt_totals', 'net_income_totals',
        ];

        // Check if field name matches monetary patterns
        return in_array($fieldName, $monetaryFields) ||
               in_array($fieldName, $monetaryArrays) ||
               str_contains($fieldName, '_total') ||
               str_contains($fieldName, '_amount') ||
               str_contains($fieldName, 'price');
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
                    'total_sales' => $this->formatNumericValue($dashboardData['total_sales'] ?? 0, 2, true),
                    'total_receipts' => $this->formatNumericValue($dashboardData['total_receipts'] ?? 0, 2, true),
                    'total_expenses' => $this->formatNumericValue($dashboardData['total_expenses'] ?? 0, 2, true),
                    'total_net_income' => $this->formatNumericValue($dashboardData['total_net_income'] ?? 0, 2, true),
                ],
                'distribution' => $dashboardData['status_distribution'] ?? [],
                'outstanding' => $this->formatArrayNumericValues($this->getTopOutstandingData($request)->toArray(), 2, false),
            ];
        }

        if (in_array('cashflow', $sections)) {
            $cashflowData = $dashboardData['chart_data'] ?? [];
            // Cashflow data contains mixed types: months (strings) and monetary values (integers in cents)
            // We need to format only the monetary arrays
            if (isset($cashflowData['invoice_totals'])) {
                $cashflowData['invoice_totals'] = $this->formatArrayNumericValues($cashflowData['invoice_totals'], 2, true);
            }
            if (isset($cashflowData['expense_totals'])) {
                $cashflowData['expense_totals'] = $this->formatArrayNumericValues($cashflowData['expense_totals'], 2, true);
            }
            if (isset($cashflowData['receipt_totals'])) {
                $cashflowData['receipt_totals'] = $this->formatArrayNumericValues($cashflowData['receipt_totals'], 2, true);
            }
            if (isset($cashflowData['net_income_totals'])) {
                $cashflowData['net_income_totals'] = $this->formatArrayNumericValues($cashflowData['net_income_totals'], 2, true);
            }
            $data['cashflow'] = $cashflowData;
        }

        if (in_array('invoices', $sections)) {
            $data['invoices'] = $this->formatArrayNumericValues($this->getInvoicesData($request));
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
            ->when($request->has('start_date'), function ($query) use ($request) {
                return $query->where('invoice_date', '>=', $request->start_date);
            })
            ->when($request->has('end_date'), function ($query) use ($request) {
                return $query->where('invoice_date', '<=', $request->end_date);
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
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Build base query for invoice status counts
        $invoiceQuery = Invoice::whereCompany();

        // Apply date filter if provided
        if ($startDate && $endDate) {
            $invoiceQuery->whereBetween('invoice_date', [$startDate, $endDate]);
        }

        $paid_count = $invoiceQuery->clone()->where('paid_status', Invoice::STATUS_PAID)->count();
        $overdue_count = $invoiceQuery->clone()->where('paid_status', Invoice::STATUS_UNPAID)
            ->where('due_date', '<', Carbon::now())
            ->count();
        $pending_count = $invoiceQuery->clone()->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
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
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Use the same logic as the working DashboardController::topOutstanding method
        $baseQuery = Invoice::whereCompany()
            ->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID]);

        // Apply date filter only if dates are provided (same as the chart behavior)
        if ($startDate && $endDate) {
            $baseQuery->whereBetween('invoice_date', [$startDate, $endDate]);
        }

        if ($type === 'clients') {
            $results = $baseQuery
                ->join('customers', 'invoices.customer_id', '=', 'customers.id')
                ->select(
                    'customers.name as label',
                    DB::raw('SUM(invoices.base_due_amount) as value')
                )
                ->groupBy('customers.name')
                ->orderByDesc(DB::raw('SUM(invoices.base_due_amount)'))
                ->limit(5);
        } else { // products
            $results = $baseQuery
                ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->join('items', 'invoice_items.item_id', '=', 'items.id')
                ->select(
                    'items.name as label',
                    DB::raw('SUM(invoice_items.base_total) as value')
                )
                ->groupBy('items.name')
                ->orderByDesc(DB::raw('SUM(invoice_items.base_total)'))
                ->limit(5);
        }

        $results = $results->get();

        // Convert cents to dollars
        $results = $results->map(function ($item) {
            $item->value = $item->value / 100;

            return $item;
        });

        return $results;
    }

    private function getCashFlowData(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // If specific dates are provided, use them for both chart data and totals
        if ($startDate && $endDate) {
            $startDateForTotals = Carbon::parse($startDate);
            $endDateForTotals = Carbon::parse($endDate);

            // Calculate totals for the specified date range
            $total_sales = Invoice::whereBetween('invoice_date', [$startDateForTotals, $endDateForTotals])->whereCompany()->sum('base_total');
            $total_receipts = Payment::whereBetween('payment_date', [$startDateForTotals, $endDateForTotals])->whereCompany()->sum('base_amount');
            $total_expenses = Expense::whereBetween('expense_date', [$startDateForTotals, $endDateForTotals])->whereCompany()->sum('base_amount');

            // For chart data with specific date range, generate monthly breakdown within that range
            $invoice_totals = [];
            $expense_totals = [];
            $receipt_totals = [];
            $net_income_totals = [];
            $months = [];

            $current = $startDateForTotals->clone()->startOfMonth();
            $endMonth = $endDateForTotals->clone()->endOfMonth();

            while ($current <= $endMonth) {
                $monthEnd = $current->clone()->endOfMonth();

                // Ensure we don't go beyond the end date
                if ($monthEnd > $endDateForTotals) {
                    $monthEnd = $endDateForTotals->clone();
                }

                $invoice_totals[] = Invoice::whereBetween('invoice_date', [$current, $monthEnd])->whereCompany()->sum('base_total');
                $expense_totals[] = Expense::whereBetween('expense_date', [$current, $monthEnd])->whereCompany()->sum('base_amount');
                $receipt_totals[] = Payment::whereBetween('payment_date', [$current, $monthEnd])->whereCompany()->sum('base_amount');
                $net_income_totals[] = end($receipt_totals) - end($expense_totals);

                $months[] = $current->translatedFormat('M');
                $current->addMonth()->startOfMonth();
            }

        } else {
            // Use fiscal year logic (original behavior) when no specific dates are provided
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
        }

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

        // Enable chart rendering support for native Excel charts
        $writer->setIncludeCharts(true);

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

        // Dashboard Summary Section
        $sheet->setCellValue('A'.$row, 'Dashboard Summary');
        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $row++;

        foreach ($data['summary'] as $key => $value) {
            $sheet->setCellValue('A'.$row, ucwords(str_replace('_', ' ', $key)));
            $sheet->setCellValue('B'.$row, $value);
            if (is_numeric($value)) {
                $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $row++;
        }
        $row++;

        // Status Distribution Section (Data for Pie Chart)
        $statusDistributionStartRow = $row;
        $sheet->setCellValue('A'.$row, 'Status Distribution');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
        $row++;

        $sheet->setCellValue('A'.$row, 'Status');
        $sheet->setCellValue('B'.$row, 'Count');
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true);
        $row++;

        $statusDistributionDataStartRow = $row;
        foreach ($data['distribution'] as $key => $value) {
            $sheet->setCellValue('A'.$row, ucfirst($key));
            $sheet->setCellValue('B'.$row, $value);
            $row++;
        }
        $statusDistributionDataEndRow = $row - 1;
        $row++;

        // Top Outstanding Invoices Section (Data for Bar Chart)
        $outstandingStartRow = $row;
        $sheet->setCellValue('A'.$row, 'Top Outstanding Invoices');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
        $row++;

        $sheet->setCellValue('A'.$row, 'Customer/Product');
        $sheet->setCellValue('B'.$row, 'Amount');
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true);
        $row++;

        $outstandingDataStartRow = $row;
        foreach ($data['outstanding'] as $item) {
            $sheet->setCellValue('A'.$row, $item['label']);
            $sheet->setCellValue('B'.$row, $item['value']);
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }
        $outstandingDataEndRow = $row - 1;

        // Auto-size columns for better readability
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);

        // Add charts if we have data
        if (! empty($data['distribution']) && array_sum($data['distribution']) > 0) {
            $this->addStatusDistributionChart($sheet, $statusDistributionDataStartRow, $statusDistributionDataEndRow);
        }

        if (! empty($data['outstanding'])) {
            $this->addOutstandingInvoicesChart($sheet, $outstandingDataStartRow, $outstandingDataEndRow);
        }
    }

    /**
     * Add a native Excel pie chart for Status Distribution
     *
     * @param  Worksheet  $sheet  The worksheet to add the chart to
     * @param  int  $dataStartRow  Starting row of the data range
     * @param  int  $dataEndRow  Ending row of the data range
     */
    private function addStatusDistributionChart(Worksheet $sheet, int $dataStartRow, int $dataEndRow)
    {
        // Define chart positioning (right side of the status distribution data)
        $chartStartCell = 'D'.($dataStartRow - 2); // Position chart next to data
        $chartEndCell = 'H'.($dataStartRow + 8);    // Chart size

        // Define data series for the pie chart (escape sheet name with spaces)
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Dashboard Overview\'!$A$'.$dataStartRow.':$A$'.$dataEndRow, null, $dataEndRow - $dataStartRow + 1),
        ];

        // For pie charts, category labels should be the same as the data labels
        $categoryLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Dashboard Overview\'!$A$'.$dataStartRow.':$A$'.$dataEndRow, null, $dataEndRow - $dataStartRow + 1),
        ];

        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'Dashboard Overview\'!$B$'.$dataStartRow.':$B$'.$dataEndRow, null, $dataEndRow - $dataStartRow + 1),
        ];

        // Create the data series
        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,              // Chart type
            null,                                    // Plot grouping (not applicable for pie)
            range(0, count($dataSeriesValues) - 1), // Plot order
            $dataSeriesLabels,                       // Series labels
            $categoryLabels,                         // Category labels (required array)
            $dataSeriesValues                        // Series values
        );

        // Configure the plot area
        $plotArea = new PlotArea(null, [$series]);

        // Create legend
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);

        // Create chart title
        $title = new Title('Invoice Status Distribution');

        // Create the chart object
        $chart = new Chart(
            'status_distribution_chart',            // Chart name
            $title,                                 // Chart title
            $legend,                               // Legend
            $plotArea,                             // Plot area
            true,                                  // Plot visible cells only
            DataSeries::EMPTY_AS_GAP,             // Display blanks as
            null,                                 // X-axis label
            null                                  // Y-axis label
        );

        // Set chart position and size
        $chart->setTopLeftPosition($chartStartCell);
        $chart->setBottomRightPosition($chartEndCell);

        // Add chart to worksheet
        $sheet->addChart($chart);
    }

    /**
     * Add a native Excel bar chart for Top Outstanding Invoices
     *
     * @param  Worksheet  $sheet  The worksheet to add the chart to
     * @param  int  $dataStartRow  Starting row of the data range
     * @param  int  $dataEndRow  Ending row of the data range
     */
    private function addOutstandingInvoicesChart(Worksheet $sheet, int $dataStartRow, int $dataEndRow)
    {
        // Define chart positioning (right side of the outstanding data)
        $chartStartCell = 'D'.($dataStartRow - 2); // Position chart next to data
        $chartEndCell = 'H'.($dataStartRow + max(8, ($dataEndRow - $dataStartRow + 4))); // Dynamic chart size

        // Define data series for the bar chart (escape sheet name with spaces)
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Dashboard Overview\'!$A$'.$dataStartRow.':$A$'.$dataEndRow, null, $dataEndRow - $dataStartRow + 1),
        ];

        $xAxisLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Dashboard Overview\'!$A$'.$dataStartRow.':$A$'.$dataEndRow, null, $dataEndRow - $dataStartRow + 1),
        ];

        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'Dashboard Overview\'!$B$'.$dataStartRow.':$B$'.$dataEndRow, null, $dataEndRow - $dataStartRow + 1),
        ];

        // Create the data series
        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,              // Chart type
            DataSeries::GROUPING_STANDARD,          // Plot grouping
            range(0, count($dataSeriesValues) - 1), // Plot order
            $dataSeriesLabels,                       // Series labels
            $xAxisLabels,                           // Category labels
            $dataSeriesValues                        // Series values
        );

        // Configure the plot area
        $plotArea = new PlotArea(null, [$series]);

        // Create legend
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);

        // Create chart title
        $title = new Title('Top 5 Outstanding Invoices');

        // Create the chart object
        $chart = new Chart(
            'outstanding_invoices_chart',           // Chart name
            $title,                                 // Chart title
            $legend,                               // Legend
            $plotArea,                             // Plot area
            true,                                  // Plot visible cells only
            DataSeries::EMPTY_AS_GAP,             // Display blanks as
            null,                                 // X-axis label
            null                                  // Y-axis label
        );

        // Set chart position and size
        $chart->setTopLeftPosition($chartStartCell);
        $chart->setBottomRightPosition($chartEndCell);

        // Add chart to worksheet
        $sheet->addChart($chart);
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

            // Format numeric columns
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0.00');

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

            // Format numeric columns
            $sheet->getStyle('F'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode('#,##0.00');

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
