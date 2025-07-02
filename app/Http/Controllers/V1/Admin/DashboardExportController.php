<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardExportController extends Controller
{
    protected $exportService;

    public function __construct(DashboardExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => ['required', 'string', Rule::in(['pdf', 'xlsx', 'csv'])],
            'sections' => ['required', 'array'],
            'sections.*' => ['string', Rule::in(['dashboard', 'cashflow', 'invoices'])],
            // Filters for the invoices table
            'customer_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'orderByField' => ['nullable', 'string'],
            'orderBy' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        return $this->exportService->generate($request);
    }

    /**
     * Export dashboard snapshot as PDF
     *
     * @return \Illuminate\Http\Response
     */
    public function exportSnapshot(Request $request)
    {
        $request->validate([
            'chartImages' => ['nullable', 'array'],
            'chartImages.statusDistribution' => ['nullable', 'string'],
            'chartImages.outstandingInvoices' => ['nullable', 'string'],
            'chartImages.predictiveCashFlow' => ['nullable', 'string'],
            'tableData' => ['nullable', 'array'],
            'tableData.invoices' => ['nullable', 'array'],
            'tableData.totalCount' => ['nullable', 'integer'],
            'tableData.filters' => ['nullable', 'array'],
            'selectedSections' => ['required', 'array'],
            'selectedSections.*' => ['string', 'in:dashboard,cashflow,invoices'],
            'dashboardData' => ['required', 'array'],
            'dashboardData.stats' => ['required', 'array'],
            'dashboardData.statusDistribution' => ['required', 'array'],
        ]);

        return $this->exportService->generateSnapshot($request);
    }
}
