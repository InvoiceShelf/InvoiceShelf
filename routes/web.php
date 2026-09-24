<?php

use App\Domains\Accounts\Models\Company;
use Illuminate\Support\Facades\Route;

require app_path('Domains/Accounts/routes/web.php');
require app_path('Domains/Contacts/routes/web.php');

// Report PDF & Expense Endpoints
// ----------------------------------------------

Route::middleware('auth:sanctum')->prefix('reports')->group(function () {
    require app_path('Domains/Reporting/routes/web.php');

    require app_path('Domains/Purchases/routes/web.php');
});

// PDF Endpoints
// ----------------------------------------------

Route::middleware('pdf-auth')->group(function () {
    require app_path('Domains/Sales/routes/pdf.php');
    require app_path('Domains/Receivables/routes/pdf.php');
});

// customer pdf endpoints for invoice, estimate and Payment
// -------------------------------------------------

Route::prefix('/customer')->group(function () {
    require app_path('Domains/Sales/routes/public.php');
    require app_path('Domains/Receivables/routes/public.php');
});

// Setup for installation of app
// ----------------------------------------------

require app_path('Platform/Operations/Installation/routes/web.php');

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

// The root is where people land by typing the address. `install` sends an
// unfinished install to the wizard and `guest` sends anyone signed in to the
// dashboard; everyone else signs in.
Route::get('/', fn () => redirect()->route('login'))
    ->name('home')
    ->middleware(['install', 'guest']);

Route::get('/reset-password/{token}', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('reset-password')->middleware(['install', 'guest']);

Route::get('/forgot-password', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('forgot-password')->middleware(['install', 'guest']);

Route::get('/login', function () {
    return view('app');
})->where('vue', '[\/\w\.-]*')->name('login')->middleware(['install', 'guest']);
