<?php

use App\Http\Controllers\Admin\Backup\BackupsController;
use App\Http\Controllers\Admin\Backup\DownloadBackupController;
use App\Http\Controllers\Admin\CountriesController;
use App\Http\Controllers\Admin\CurrenciesController;
use App\Http\Controllers\Admin\Modules\ModuleInstallationController;
use App\Http\Controllers\Admin\Modules\ModulesController;
use App\Http\Controllers\Admin\Settings\DiskController;
use App\Http\Controllers\Admin\Settings\GetSettingsController;
use App\Http\Controllers\Admin\Settings\MailConfigurationController;
use App\Http\Controllers\Admin\Settings\PDFConfigurationController;
use App\Http\Controllers\Admin\Settings\UpdateSettingsController;
use App\Http\Controllers\Admin\Update\CheckVersionController;
use App\Http\Controllers\Admin\Update\CopyFilesController;
use App\Http\Controllers\Admin\Update\DeleteFilesController;
use App\Http\Controllers\Admin\Update\DownloadUpdateController;
use App\Http\Controllers\Admin\Update\FinishUpdateController;
use App\Http\Controllers\Admin\Update\MigrateUpdateController;
use App\Http\Controllers\Admin\Update\UnzipUpdateController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\Company\Auth\AuthController;
use App\Http\Controllers\Company\Auth\ForgotPasswordController;
use App\Http\Controllers\Company\Auth\ResetPasswordController;
use App\Http\Controllers\Company\Company\CompaniesController;
use App\Http\Controllers\Company\Customer\CustomersController;
use App\Http\Controllers\Company\Customer\CustomerStatsController;
use App\Http\Controllers\Company\CustomField\CustomFieldsController;
use App\Http\Controllers\Company\Dashboard\DashboardController;
use App\Http\Controllers\Company\Estimate\EstimatesController;
use App\Http\Controllers\Company\Estimate\EstimateTemplatesController;
use App\Http\Controllers\Company\ExchangeRate\ExchangeRateProviderController;
use App\Http\Controllers\Company\Expense\ExpenseCategoriesController;
use App\Http\Controllers\Company\Expense\ExpensesController;
use App\Http\Controllers\Company\General\BootstrapController;
use App\Http\Controllers\Company\General\ConfigController;
use App\Http\Controllers\Company\General\FormatsController;
use App\Http\Controllers\Company\General\NotesController;
use App\Http\Controllers\Company\General\SearchController;
use App\Http\Controllers\Company\General\SerialNumberController;
use App\Http\Controllers\Company\Invoice\InvoicesController;
use App\Http\Controllers\Company\Invoice\InvoiceTemplatesController;
use App\Http\Controllers\Company\Item\ItemsController;
use App\Http\Controllers\Company\Item\UnitsController;
use App\Http\Controllers\Company\Payment\PaymentMethodsController;
use App\Http\Controllers\Company\Payment\PaymentsController;
use App\Http\Controllers\Company\RecurringInvoice\RecurringInvoiceController;
use App\Http\Controllers\Company\RecurringInvoice\RecurringInvoiceFrequencyController;
use App\Http\Controllers\Company\Role\AbilitiesController;
use App\Http\Controllers\Company\Role\RolesController;
use App\Http\Controllers\Company\Settings\CompanyController;
use App\Http\Controllers\Company\Settings\CompanyCurrencyCheckTransactionsController;
use App\Http\Controllers\Company\Settings\CompanyMailConfigurationController;
use App\Http\Controllers\Company\Settings\CompanySettingsController;
use App\Http\Controllers\Company\Settings\TaxTypesController;
use App\Http\Controllers\Company\Settings\UserProfileController;
use App\Http\Controllers\Company\Settings\UserSettingsController;
use App\Http\Controllers\Company\Users\UsersController;
use App\Http\Controllers\CustomerPortal\Auth\ForgotPasswordController as AuthForgotPasswordController;
use App\Http\Controllers\CustomerPortal\Auth\ResetPasswordController as AuthResetPasswordController;
use App\Http\Controllers\CustomerPortal\Estimate\AcceptEstimateController as CustomerAcceptEstimateController;
use App\Http\Controllers\CustomerPortal\Estimate\EstimatesController as CustomerEstimatesController;
use App\Http\Controllers\CustomerPortal\Expense\ExpensesController as CustomerExpensesController;
use App\Http\Controllers\CustomerPortal\General\BootstrapController as CustomerBootstrapController;
use App\Http\Controllers\CustomerPortal\General\DashboardController as CustomerDashboardController;
use App\Http\Controllers\CustomerPortal\General\ProfileController as CustomerProfileController;
use App\Http\Controllers\CustomerPortal\Invoice\InvoicesController as CustomerInvoicesController;
use App\Http\Controllers\CustomerPortal\Payment\PaymentMethodController;
use App\Http\Controllers\CustomerPortal\Payment\PaymentsController as CustomerPaymentsController;
use App\Http\Controllers\Setup\AppDomainController;
use App\Http\Controllers\Setup\DatabaseConfigurationController;
use App\Http\Controllers\Setup\FilePermissionsController;
use App\Http\Controllers\Setup\FinishController;
use App\Http\Controllers\Setup\LanguagesController;
use App\Http\Controllers\Setup\LoginController;
use App\Http\Controllers\Setup\OnboardingWizardController;
use App\Http\Controllers\Setup\RequirementsController;
use App\Http\Controllers\Webhook\CronJobController;
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

    Route::get('/app/version', AppVersionController::class);

    // Authentication & Password Reset
    // ----------------------------------

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

        // Send reset password mail
        Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:10,2');

        // handle reset password form process
        Route::post('reset/password', [ResetPasswordController::class, 'reset']);
    });

    // Countries
    // ----------------------------------

    Route::get('/countries', CountriesController::class);

    // Onboarding
    // ----------------------------------

    Route::middleware(['redirect-if-installed'])->prefix('installation')->group(function () {
        Route::get('/wizard-step', [OnboardingWizardController::class, 'getStep']);

        Route::post('/wizard-step', [OnboardingWizardController::class, 'updateStep']);

        Route::post('/wizard-language', [OnboardingWizardController::class, 'saveLanguage']);

        Route::get('/languages', [LanguagesController::class, 'languages']);

        Route::get('/requirements', [RequirementsController::class, 'requirements']);

        Route::get('/permissions', [FilePermissionsController::class, 'permissions']);

        Route::post('/database/config', [DatabaseConfigurationController::class, 'saveDatabaseEnvironment']);

        Route::get('/database/config', [DatabaseConfigurationController::class, 'getDatabaseEnvironment']);

        Route::put('/set-domain', AppDomainController::class);

        Route::post('/login', LoginController::class);

        Route::post('/finish', FinishController::class);
    });

    // Super Admin
    // ----------------------------------

    Route::middleware(['auth:sanctum', 'super-admin'])->prefix('super-admin')->group(function () {
        Route::get('companies', [App\Http\Controllers\Admin\CompaniesController::class, 'index']);
        Route::get('companies/{company}', [App\Http\Controllers\Admin\CompaniesController::class, 'show']);
        Route::put('companies/{company}', [App\Http\Controllers\Admin\CompaniesController::class, 'update']);

        Route::get('users', [App\Http\Controllers\Admin\UsersController::class, 'index']);
        Route::get('users/{user}', [App\Http\Controllers\Admin\UsersController::class, 'show']);
        Route::put('users/{user}', [App\Http\Controllers\Admin\UsersController::class, 'update']);
        Route::post('users/{user}/impersonate', [App\Http\Controllers\Admin\UsersController::class, 'impersonate']);
    });

    // Stop impersonation - uses auth:sanctum only (the impersonated user's token, not super-admin)
    Route::middleware(['auth:sanctum'])->prefix('super-admin')->group(function () {
        Route::post('stop-impersonating', [App\Http\Controllers\Admin\UsersController::class, 'stopImpersonating']);
    });

    Route::middleware(['auth:sanctum', 'company'])->group(function () {
        Route::middleware(['bouncer'])->group(function () {

            // Bootstrap
            // ----------------------------------

            Route::get('/bootstrap', BootstrapController::class);

            // Currencies
            // ----------------------------------

            Route::prefix('/currencies')->group(function () {
                Route::get('/used', [ExchangeRateProviderController::class, 'usedCurrenciesWithoutRate']);

                Route::post('/bulk-update-exchange-rate', [ExchangeRateProviderController::class, 'bulkUpdate']);
            });

            // Dashboard
            // ----------------------------------

            Route::get('/dashboard', DashboardController::class);

            // Auth check
            // ----------------------------------

            Route::get('/auth/check', [AuthController::class, 'check']);

            // Search users
            // ----------------------------------

            Route::get('/search', SearchController::class);

            Route::get('/search/user', [SearchController::class, 'users']);

            // MISC
            // ----------------------------------

            Route::get('/config', ConfigController::class);

            Route::get('/currencies', CurrenciesController::class);

            Route::get('/timezones', [FormatsController::class, 'timezones']);

            Route::get('/date/formats', [FormatsController::class, 'dateFormats']);

            Route::get('/time/formats', [FormatsController::class, 'timeFormats']);

            Route::get('/next-number', [SerialNumberController::class, 'nextNumber']);

            Route::get('/number-placeholders', [SerialNumberController::class, 'placeholders']);

            Route::get('/current-company', [CompaniesController::class, 'show']);

            // Customers
            // ----------------------------------

            Route::post('/customers/delete', [CustomersController::class, 'delete']);

            Route::get('customers/{customer}/stats', CustomerStatsController::class);

            Route::resource('customers', CustomersController::class);

            // Items
            // ----------------------------------

            Route::post('/items/delete', [ItemsController::class, 'delete']);

            Route::resource('items', ItemsController::class);

            Route::resource('units', UnitsController::class);

            // Invoices
            // -------------------------------------------------

            Route::get('/invoices/{invoice}/send/preview', [InvoicesController::class, 'sendPreview']);

            Route::post('/invoices/{invoice}/send', [InvoicesController::class, 'send']);

            Route::post('/invoices/{invoice}/clone', [InvoicesController::class, 'clone']);

            Route::post('/invoices/{invoice}/status', [InvoicesController::class, 'changeStatus']);

            Route::post('/invoices/delete', [InvoicesController::class, 'delete']);

            Route::get('/invoices/templates', InvoiceTemplatesController::class);

            Route::apiResource('invoices', InvoicesController::class);

            // Recurring Invoice
            // -------------------------------------------------

            Route::get('/recurring-invoice-frequency', RecurringInvoiceFrequencyController::class);

            Route::post('/recurring-invoices/delete', [RecurringInvoiceController::class, 'delete']);

            Route::apiResource('recurring-invoices', RecurringInvoiceController::class);

            // Estimates
            // -------------------------------------------------

            Route::get('/estimates/{estimate}/send/preview', [EstimatesController::class, 'sendPreview']);

            Route::post('/estimates/{estimate}/send', [EstimatesController::class, 'send']);

            Route::post('/estimates/{estimate}/clone', [EstimatesController::class, 'clone']);

            Route::post('/estimates/{estimate}/status', [EstimatesController::class, 'changeStatus']);

            Route::post('/estimates/{estimate}/convert-to-invoice', [EstimatesController::class, 'convertToInvoice']);

            Route::get('/estimates/templates', EstimateTemplatesController::class);

            Route::post('/estimates/delete', [EstimatesController::class, 'delete']);

            Route::apiResource('estimates', EstimatesController::class);

            // Expenses
            // ----------------------------------

            Route::get('/expenses/{expense}/show/receipt', [ExpensesController::class, 'showReceipt']);

            Route::post('/expenses/{expense}/upload/receipts', [ExpensesController::class, 'uploadReceipt']);

            Route::post('/expenses/delete', [ExpensesController::class, 'delete']);

            Route::apiResource('expenses', ExpensesController::class);

            Route::apiResource('categories', ExpenseCategoriesController::class);

            // Payments
            // ----------------------------------

            Route::get('/payments/{payment}/send/preview', [PaymentsController::class, 'sendPreview']);

            Route::post('/payments/{payment}/send', [PaymentsController::class, 'send']);

            Route::post('/payments/delete', [PaymentsController::class, 'delete']);

            Route::apiResource('payments', PaymentsController::class);

            Route::apiResource('payment-methods', PaymentMethodsController::class);

            // Custom fields
            // ----------------------------------

            Route::resource('custom-fields', CustomFieldsController::class);

            // Backup & Disk
            // ----------------------------------

            Route::apiResource('backups', BackupsController::class);

            Route::apiResource('/disks', DiskController::class);

            Route::get('download-backup', DownloadBackupController::class);

            Route::get('/disk/drivers', [DiskController::class, 'getDiskDrivers']);

            // Exchange Rate
            // ----------------------------------

            Route::get('/currencies/{currency}/exchange-rate', [ExchangeRateProviderController::class, 'getRate']);

            Route::get('/currencies/{currency}/active-provider', [ExchangeRateProviderController::class, 'activeProvider']);

            Route::get('/used-currencies', [ExchangeRateProviderController::class, 'usedCurrencies']);

            Route::get('/supported-currencies', [ExchangeRateProviderController::class, 'supportedCurrencies']);

            Route::apiResource('exchange-rate-providers', ExchangeRateProviderController::class);

            // Settings
            // ----------------------------------

            Route::get('/me', [UserProfileController::class, 'show']);

            Route::put('/me', [UserProfileController::class, 'update']);

            Route::get('/me/settings', [UserSettingsController::class, 'show']);

            Route::put('/me/settings', [UserSettingsController::class, 'update']);

            Route::post('/me/upload-avatar', [UserProfileController::class, 'uploadAvatar']);

            Route::put('/company', [CompanyController::class, 'updateCompany']);

            Route::post('/company/upload-logo', [CompanyController::class, 'uploadCompanyLogo']);

            Route::get('/company/settings', [CompanySettingsController::class, 'show']);

            Route::post('/company/settings', [CompanySettingsController::class, 'update']);

            Route::get('/settings', GetSettingsController::class);

            Route::post('/settings', UpdateSettingsController::class);

            Route::get('/company/has-transactions', CompanyCurrencyCheckTransactionsController::class);

            // Mails
            // ----------------------------------

            Route::get('/mail/drivers', [MailConfigurationController::class, 'getMailDrivers']);

            Route::get('/mail/config', [MailConfigurationController::class, 'getMailEnvironment']);

            Route::post('/mail/config', [MailConfigurationController::class, 'saveMailEnvironment']);

            Route::post('/mail/test', [MailConfigurationController::class, 'testEmailConfig']);

            Route::get('/company/mail/config', [CompanyMailConfigurationController::class, 'getDefaultConfig']);

            Route::get('/company/mail/company-config', [CompanyMailConfigurationController::class, 'getMailConfig']);
            Route::post('/company/mail/company-config', [CompanyMailConfigurationController::class, 'saveMailConfig']);
            Route::post('/company/mail/company-test', [CompanyMailConfigurationController::class, 'testMailConfig']);

            // PDF Generation
            // ----------------------------------

            Route::get('/pdf/drivers', [PDFConfigurationController::class, 'getDrivers']);

            Route::get('/pdf/config', [PDFConfigurationController::class, 'getEnvironment']);

            Route::post('/pdf/config', [PDFConfigurationController::class, 'saveEnvironment']);

            Route::apiResource('notes', NotesController::class);

            // Tax Types
            // ----------------------------------

            Route::apiResource('tax-types', TaxTypesController::class);

            // Roles
            // ----------------------------------

            Route::get('abilities', AbilitiesController::class);

            Route::apiResource('roles', RolesController::class);
        });

        // Self Update
        // ----------------------------------

        Route::get('/check/update', CheckVersionController::class);

        Route::post('/update/download', DownloadUpdateController::class);

        Route::post('/update/unzip', UnzipUpdateController::class);

        Route::post('/update/copy', CopyFilesController::class);

        Route::post('/update/delete', DeleteFilesController::class);

        Route::post('/update/migrate', MigrateUpdateController::class);

        Route::post('/update/finish', FinishUpdateController::class);

        // Companies
        // -------------------------------------------------

        Route::post('companies', [CompaniesController::class, 'store']);

        Route::post('/transfer/ownership/{user}', [CompaniesController::class, 'transferOwnership']);

        Route::post('companies/delete', [CompaniesController::class, 'destroy']);

        Route::get('companies', [CompaniesController::class, 'getUserCompanies']);

        // Users
        // ----------------------------------

        Route::post('/users/delete', [UsersController::class, 'delete']);

        Route::apiResource('/users', UsersController::class);

        // Modules
        // ----------------------------------

        Route::prefix('/modules')->group(function () {
            Route::get('/', [ModulesController::class, 'index']);
            Route::get('/check', [ModulesController::class, 'checkToken']);
            Route::get('/{module}', [ModulesController::class, 'show']);
            Route::post('/{module}/enable', [ModulesController::class, 'enable']);
            Route::post('/{module}/disable', [ModulesController::class, 'disable']);

            Route::post('/download', [ModuleInstallationController::class, 'download']);
            Route::post('/upload', [ModuleInstallationController::class, 'upload']);
            Route::post('/unzip', [ModuleInstallationController::class, 'unzip']);
            Route::post('/copy', [ModuleInstallationController::class, 'copy']);
            Route::post('/complete', [ModuleInstallationController::class, 'complete']);
        });
    });

    Route::prefix('/{company:slug}/customer')->group(function () {

        // Authentication & Password Reset
        // ----------------------------------

        Route::prefix('auth')->group(function () {

            // Send reset password mail
            Route::post('password/email', [AuthForgotPasswordController::class, 'sendResetLinkEmail']);

            // handle reset password form process
            Route::post('reset/password', [AuthResetPasswordController::class, 'reset'])->name('customer.password.reset');
        });

        // Invoices, Estimates, Payments and Expenses endpoints
        // -------------------------------------------------------

        Route::middleware(['auth:customer', 'customer-portal'])->group(function () {
            Route::get('/bootstrap', CustomerBootstrapController::class);

            Route::get('/dashboard', CustomerDashboardController::class);

            Route::get('invoices', [CustomerInvoicesController::class, 'index']);

            Route::get('invoices/{id}', [CustomerInvoicesController::class, 'show']);

            Route::post('/estimate/{estimate}/status', CustomerAcceptEstimateController::class);

            Route::get('estimates', [CustomerEstimatesController::class, 'index']);

            Route::get('estimates/{id}', [CustomerEstimatesController::class, 'show']);

            Route::get('payments', [CustomerPaymentsController::class, 'index']);

            Route::get('payments/{id}', [CustomerPaymentsController::class, 'show']);

            Route::get('/payment-method', PaymentMethodController::class);

            Route::get('expenses', [CustomerExpensesController::class, 'index']);

            Route::get('expenses/{id}', [CustomerExpensesController::class, 'show']);

            Route::post('/profile', [CustomerProfileController::class, 'updateProfile']);

            Route::get('/me', [CustomerProfileController::class, 'getUser']);

            Route::get('/countries', CountriesController::class);
        });
    });
});

Route::get('/cron', CronJobController::class)->middleware('cron-job');
