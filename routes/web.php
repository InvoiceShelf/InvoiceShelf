<?php

use App\Http\Controllers\Company\Auth\LoginController;
use App\Http\Controllers\Company\Expense\ExpensesController;
use App\Http\Controllers\Company\Report\CustomerSalesReportController;
use App\Http\Controllers\Company\Report\ExpensesReportController;
use App\Http\Controllers\Company\Report\ItemSalesReportController;
use App\Http\Controllers\Company\Report\ProfitLossReportController;
use App\Http\Controllers\Company\Report\TaxSummaryReportController;
use App\Http\Controllers\CustomerPortal\Auth\LoginController as CustomerLoginController;
use App\Http\Controllers\CustomerPortal\EstimatePdfController as CustomerEstimatePdfController;
use App\Http\Controllers\CustomerPortal\InvoicePdfController as CustomerInvoicePdfController;
use App\Http\Controllers\CustomerPortal\PaymentPdfController as CustomerPaymentPdfController;
use App\Http\Controllers\Modules\ScriptController;
use App\Http\Controllers\Modules\StyleController;
use App\Http\Controllers\Pdf\DocumentPdfController;
use App\Http\Controllers\Setup\SessionLoginController;
use App\Models\Company;
use App\Models\CompanyInvitation;
use Illuminate\Support\Facades\Route;

// Module Asset Includes
// ----------------------------------------------

Route::get('/modules/styles/{style}', StyleController::class);

Route::get('/modules/scripts/{script}', ScriptController::class);

// Admin Auth
// ----------------------------------------------

Route::post('login', [LoginController::class, 'login']);

Route::post('auth/logout', function () {
    Auth::guard('web')->logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();
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
    // ----------------------------------
    Route::get('/sales/customers/{hash}', CustomerSalesReportController::class);

    // sales report by items
    // ----------------------------------
    Route::get('/sales/items/{hash}', ItemSalesReportController::class);

    // report for expenses
    // ----------------------------------
    Route::get('/expenses/{hash}', ExpensesReportController::class);

    // report for tax summary
    // ----------------------------------
    Route::get('/tax-summary/{hash}', TaxSummaryReportController::class);

    // report for profit and loss
    // ----------------------------------
    Route::get('/profit-loss/{hash}', ProfitLossReportController::class);

    // download expense receipt
    // -------------------------------------------------
    Route::get('/expenses/{expense}/download-receipt', [ExpensesController::class, 'downloadReceipt']);
    Route::get('/expenses/{expense}/receipt', [ExpensesController::class, 'showReceipt']);
});

// PDF Endpoints
// ----------------------------------------------

// Invitation email link handlers
// -------------------------------------------------

Route::get('/invitations/{token}/decline', function (string $token) {
    $invitation = CompanyInvitation::where('token', $token)->pending()->first();

    if (! $invitation) {
        return view('app')->with(['message' => 'Invitation not found or already expired.']);
    }

    $invitation->update(['status' => CompanyInvitation::STATUS_DECLINED]);

    return view('app')->with(['message' => 'Invitation declined.']);
});

Route::middleware('pdf-auth')->group(function () {

    //  invoice pdf
    // -------------------------------------------------
    Route::get('/invoices/pdf/{invoice:unique_hash}', [DocumentPdfController::class, 'invoice']);
    Route::get('/estimates/pdf/{estimate:unique_hash}', [DocumentPdfController::class, 'estimate']);
    Route::get('/payments/pdf/{payment:unique_hash}', [DocumentPdfController::class, 'payment']);
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
})->name('install')
    ->middleware(['redirect-if-installed']);

// Catch-all for installation wizard sub-routes (language, requirements,
// permissions, database, domain, mail, account, company, preferences).
// The Vue Router handles the actual step rendering on the SPA side; this
// just makes sure deep links and hard refreshes inside the wizard hit the
// SPA shell instead of 404ing.
Route::get('/installation/{vue?}', function () {
    return view('app');
})->where('vue', '.*')
    ->middleware(['redirect-if-installed']);

Route::post('/installation/session-login', SessionLoginController::class)
    ->middleware(['redirect-if-installed', 'auth:sanctum']);

// Registration via invitation (serves SPA)
// -------------------------------------------------

Route::get('/register', function () {
    return view('app');
})->middleware(['install']);

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
