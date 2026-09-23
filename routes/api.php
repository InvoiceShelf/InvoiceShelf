<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// ping
// ----------------------------------

Route::get('ping', function () {
    return response()->json([
        'success' => 'invoiceshelf-self-hosted',
    ]);
})->name('ping');

// Version 1 endpoints
// --------------------------------------
Route::prefix('/v1')->group(function () {

    // App version
    // ----------------------------------

    require app_path('Platform/Operations/routes/version.php');

    // Authentication & Password Reset
    // ----------------------------------

    require app_path('Domains/Accounts/routes/public.php');

    // Countries
    // ----------------------------------

    require app_path('Domains/Contacts/routes/public.php');

    // Onboarding
    // ----------------------------------

    Route::middleware(['redirect-if-installed'])->prefix('installation')->group(function () {
        require app_path('Platform/Operations/Installation/routes/api.php');
    });

    // Super Admin
    // ----------------------------------

    Route::middleware(['auth:sanctum', 'super-admin'])->prefix('super-admin')->group(function () {
        require app_path('Platform/Operations/routes/admin.php');
        require app_path('Domains/Accounts/routes/admin.php');
        require app_path('Platform/Mcp/routes/admin.php');

        // Currencies are installation-wide reference data, so the list and
        // its refresh live here rather than under a company.

        require app_path('Domains/Money/routes/admin.php');
    });

    // Stop impersonation - uses auth:sanctum only (the impersonated user's token, not super-admin)
    Route::middleware(['auth:sanctum'])->prefix('super-admin')->group(function () {
        require app_path('Domains/Accounts/routes/impersonation.php');
    });

    Route::middleware(['auth:sanctum', 'company'])->group(function () {
        Route::middleware(['bouncer'])->group(function () {
            require app_path('Domains/Accounts/routes/company.php');
            require app_path('Platform/Operations/routes/company.php');

            // Currencies
            // ----------------------------------

            require app_path('Domains/Money/routes/company.php');

            // Reporting and customers
            // ----------------------------------

            require app_path('Domains/Reporting/routes/company.php');
            require app_path('Domains/Contacts/routes/company.php');

            // Items
            // ----------------------------------

            require app_path('Domains/Catalog/routes/company.php');

            // Sales documents
            // -------------------------------------------------

            require app_path('Domains/Sales/routes/company.php');

            // Expenses
            // ----------------------------------

            require app_path('Domains/Purchases/routes/company.php');

            // Payments
            // ----------------------------------

            require app_path('Domains/Receivables/routes/company.php');

            // Custom fields
            // ----------------------------------

            require app_path('Domains/Metadata/routes/company.php');

            // Backup & Disk
            // ----------------------------------

            require app_path('Platform/Storage/routes/company.php');

            // PDF rendering and fonts
            // ----------------------------------

            require app_path('Platform/Pdf/routes/admin.php');

            require app_path('Platform/Operations/routes/settings.php');

            // Mails
            // ----------------------------------

            require app_path('Platform/Mail/routes/company.php');

            // Tax Types
            // ----------------------------------

            require app_path('Domains/Taxation/routes/company.php');

        });

        // Self Update
        // ----------------------------------
        // Disabled inside the official Docker image — containers upgrade via
        // `docker compose pull`, not the in-app updater (see EnsureNotContainerized).

        require app_path('Platform/Operations/routes/updater.php');

        require app_path('Domains/Accounts/routes/management.php');

        // Connected AI apps (MCP)
        // ----------------------------------

        require app_path('Platform/Mcp/routes/api.php');

    });

    Route::prefix('/{company:slug}/customer')->group(function () {

        // Authentication & Password Reset
        // ----------------------------------

        require app_path('Domains/Contacts/routes/customer-public.php');

        // Invoices, Estimates, Payments and Expenses endpoints
        // -------------------------------------------------------

        Route::middleware(['auth:customer', 'customer-portal'])->group(function () {
            require app_path('Domains/Contacts/routes/customer.php');

            require app_path('Domains/Sales/routes/customer.php');

            require app_path('Domains/Receivables/routes/customer.php');

            require app_path('Domains/Purchases/routes/customer.php');

        });
    });
});

require app_path('Platform/Operations/routes/webhooks.php');
