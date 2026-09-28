<?php

use App\Domains\Reporting\Http\Controllers\CustomerSalesReportController;
use App\Domains\Reporting\Http\Controllers\CustomerStatementReportController;
use App\Domains\Reporting\Http\Controllers\ExpensesReportController;
use App\Domains\Reporting\Http\Controllers\ItemSalesReportController;
use App\Domains\Reporting\Http\Controllers\ProfitLossReportController;
use App\Domains\Reporting\Http\Controllers\PurchasesReportController;
use App\Domains\Reporting\Http\Controllers\TaxSummaryReportController;
use Illuminate\Support\Facades\Route;

Route::get('/customers/{customer}/statement', CustomerStatementReportController::class);

// Each financial report is addressed by the hash of the company it covers.
// The hash names the company and nothing more: the controllers still check
// the session for the report ability and for membership of that company.
$hashedReports = [
    '/sales/customers' => CustomerSalesReportController::class,
    '/sales/items' => ItemSalesReportController::class,
    '/expenses' => ExpensesReportController::class,
    '/tax-summary' => TaxSummaryReportController::class,
    '/purchases' => PurchasesReportController::class,
    '/profit-loss' => ProfitLossReportController::class,
];

foreach ($hashedReports as $path => $controller) {
    Route::get($path.'/{hash}', $controller);
}
