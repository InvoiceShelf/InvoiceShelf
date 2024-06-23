<?php

use Illuminate\Support\Facades\Route;
use InvoiceShelf\Http\Controllers\ScheduleController;
use InvoiceShelf\Http\Controllers\V1\Admin\Auth\LoginController;
use InvoiceShelf\Http\Controllers\V1\Admin\Expense\ShowReceiptController;
use InvoiceShelf\Http\Controllers\V1\Admin\Report\CustomerSalesReportController;
use InvoiceShelf\Http\Controllers\V1\Admin\Report\ExpensesReportController;
use InvoiceShelf\Http\Controllers\V1\Admin\Report\ItemSalesReportController;
use InvoiceShelf\Http\Controllers\V1\Admin\Report\ProfitLossReportController;
use InvoiceShelf\Http\Controllers\V1\Admin\Report\TaxSummaryReportController;
use InvoiceShelf\Http\Controllers\V1\Customer\Auth\LoginController as CustomerLoginController;
use InvoiceShelf\Http\Controllers\V1\Customer\EstimatePdfController as CustomerEstimatePdfController;
use InvoiceShelf\Http\Controllers\V1\Customer\InvoicePdfController as CustomerInvoicePdfController;
use InvoiceShelf\Http\Controllers\V1\Customer\PaymentPdfController as CustomerPaymentPdfController;
use InvoiceShelf\Http\Controllers\V1\Modules\ScriptController;
use InvoiceShelf\Http\Controllers\V1\Modules\StyleController;
use InvoiceShelf\Http\Controllers\V1\PDF\DownloadReceiptController;
use InvoiceShelf\Http\Controllers\V1\PDF\EstimatePdfController;
use InvoiceShelf\Http\Controllers\V1\PDF\InvoicePdfController;
use InvoiceShelf\Http\Controllers\V1\PDF\PaymentPdfController;
use InvoiceShelf\Models\Company;

// Module Asset Includes
// ----------------------------------------------

Route::get('/modules/styles/{style}', StyleController::class);

Route::get('/modules/scripts/{script}', ScriptController::class);

// Admin Auth
// ----------------------------------------------

Route::post('login', [LoginController::class, 'login']);

Route::post('auth/logout', function () {
    Auth::guard('web')->logout();
});

// Customer auth
// ----------------------------------------------

Route::post('/{company:slug}/customer/login', CustomerLoginController::class);

Route::post('/{company:slug}/customer/logout', function () {
    Auth::guard('customer')->logout();
});

// Report PDF & Expense Endpoints
// ----------------------------------------------

Route::middleware('auth:sanctum')->prefix('reports')->group(function () {

    // sales report by customer
    //----------------------------------
    Route::get('/sales/customers/{hash}', CustomerSalesReportController::class);

    // sales report by items
    //----------------------------------
    Route::get('/sales/items/{hash}', ItemSalesReportController::class);

    // report for expenses
    //----------------------------------
    Route::get('/expenses/{hash}', ExpensesReportController::class);

    // report for tax summary
    //----------------------------------
    Route::get('/tax-summary/{hash}', TaxSummaryReportController::class);

    // report for profit and loss
    //----------------------------------
    Route::get('/profit-loss/{hash}', ProfitLossReportController::class);

    // download expense receipt
    // -------------------------------------------------
    Route::get('/expenses/{expense}/download-receipt', DownloadReceiptController::class);
    Route::get('/expenses/{expense}/receipt', ShowReceiptController::class);
});

// PDF Endpoints
// ----------------------------------------------

Route::middleware('pdf-auth')->group(function () {

    //  invoice pdf
    // -------------------------------------------------
    Route::get('/invoices/pdf/{invoice:unique_hash}', InvoicePdfController::class);

    // estimate pdf
    // -------------------------------------------------
    Route::get('/estimates/pdf/{estimate:unique_hash}', EstimatePdfController::class);

    // payment pdf
    // -------------------------------------------------
    Route::get('/payments/pdf/{payment:unique_hash}', PaymentPdfController::class);
});

// customer pdf endpoints for invoice, estimate and Payment
// -------------------------------------------------

Route::prefix('/customer')->group(function () {
    Route::get('/invoices/{email_log:token}', [CustomerInvoicePdfController::class, 'getInvoice']);
    Route::get('/invoices/view/{email_log:token}', [CustomerInvoicePdfController::class, 'getPdf'])->name('invoice');

    Route::get('/estimates/{email_log:token}', [CustomerEstimatePdfController::class, 'getEstimate']);
    Route::get('/estimates/view/{email_log:token}', [CustomerEstimatePdfController::class, 'getPdf'])->name('estimate');

    Route::get('/payments/{email_log:token}', [CustomerPaymentPdfController::class, 'getPayment']);
    Route::get('/payments/view/{email_log:token}', [CustomerPaymentPdfController::class, 'getPdf'])->name('payment');
});

// Setup for installation of app
// ----------------------------------------------

Route::get('/installation', function () {
    return view('app');
})->name('install')->middleware('redirect-if-installed');

// Move other http requests to the Vue App
// -------------------------------------------------

Route::get('/admin/{vue?}', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('admin.dashboard')->middleware(['install', 'redirect-if-unauthenticated']);

Route::get('{company:slug}/customer/{vue?}', function (Company $company) {
    return view('app')->with([
        'customer_logo' => get_company_setting('customer_portal_logo', $company->id),
        'current_theme' => get_company_setting('customer_portal_theme', $company->id),
        'customer_page_title' => get_company_setting('customer_portal_page_title', $company->id),
    ]);
})->where('vue', '[\/\w\.-]*')->name('customer.dashboard')->middleware(['install']);

Route::get('/', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('home')->middleware(['install', 'guest']);

Route::get('/reset-password/{token}', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('reset-password')->middleware(['install', 'guest']);

Route::get('/forgot-password', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('forgot-password')->middleware(['install', 'guest']);

Route::get('/login', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('login')->middleware(['install', 'guest']);

//SCHEDULES
Route::get('schedules', [ScheduleController::class, 'index'])->name('schedule.index')->middleware(['redirect-if-unauthenticated']);
Route::post('schedules', [ScheduleController::class, 'store'])->name('schedule.store')->middleware(['redirect-if-unauthenticated']);
Route::put('schedules/{id}', [ScheduleController::class, 'update'])->name('schedule.update')->middleware(['redirect-if-unauthenticated']);
Route::put('schedules/{id}/dragdrop', [ScheduleController::class, 'dragdrop'])->name('schedule.dragdrop');
Route::put('schedules/{id}/resize', [ScheduleController::class, 'resize'])->name('schedule.resize')->middleware(['redirect-if-unauthenticated']);
Route::delete('schedules/{id}', [ScheduleController::class, 'delete'])->name('schedule.delete')->middleware(['redirect-if-unauthenticated']);
Route::get('get-installers', [ScheduleController::class, 'getInstallers'])->name('schedule.get-installers')->middleware(['redirect-if-unauthenticated']);
Route::get('get-customers', [ScheduleController::class, 'getCustomers'])->name('schedule.get-customers')->middleware(['redirect-if-unauthenticated']);
Route::get('get-token', [ScheduleController::class, 'getToken'])->name('schedule.get-token')->middleware(['redirect-if-unauthenticated']);
