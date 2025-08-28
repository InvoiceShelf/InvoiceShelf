<?php

use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\V1\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\V1\Admin\Auth\ResetPasswordController;
use App\Http\Controllers\V1\Admin\Backup\BackupsController;
use App\Http\Controllers\V1\Admin\Backup\DownloadBackupController;
use App\Http\Controllers\V1\Admin\Company\CompaniesController;
use App\Http\Controllers\V1\Admin\Company\CompanyController as AdminCompanyController;
use App\Http\Controllers\V1\Admin\Customer\CustomersController;
use App\Http\Controllers\V1\Admin\Customer\CustomerStatsController;
use App\Http\Controllers\V1\Admin\CustomField\CustomFieldsController;
use App\Http\Controllers\V1\Admin\Dashboard\DashboardController;
use App\Http\Controllers\V1\Admin\Estimate\ChangeEstimateStatusController;
use App\Http\Controllers\V1\Admin\Estimate\CloneEstimateController;
use App\Http\Controllers\V1\Admin\Estimate\ConvertEstimateController;
use App\Http\Controllers\V1\Admin\Estimate\EstimatesController;
use App\Http\Controllers\V1\Admin\Estimate\EstimateTemplatesController;
use App\Http\Controllers\V1\Admin\Estimate\SendEstimateController;
use App\Http\Controllers\V1\Admin\Estimate\SendEstimatePreviewController;
use App\Http\Controllers\V1\Admin\ExchangeRate\ExchangeRateProviderController;
use App\Http\Controllers\V1\Admin\ExchangeRate\GetActiveProviderController;
use App\Http\Controllers\V1\Admin\ExchangeRate\GetExchangeRateController;
use App\Http\Controllers\V1\Admin\ExchangeRate\GetSupportedCurrenciesController;
use App\Http\Controllers\V1\Admin\ExchangeRate\GetUsedCurrenciesController;
use App\Http\Controllers\V1\Admin\Expense\ExpenseCategoriesController;
use App\Http\Controllers\V1\Admin\Expense\ExpensesController;
use App\Http\Controllers\V1\Admin\Expense\ShowReceiptController;
use App\Http\Controllers\V1\Admin\Expense\UploadReceiptController;
use App\Http\Controllers\V1\Admin\General\BootstrapController;
use App\Http\Controllers\V1\Admin\General\BulkExchangeRateController;
use App\Http\Controllers\V1\Admin\General\ConfigController;
use App\Http\Controllers\V1\Admin\General\CountriesController;
use App\Http\Controllers\V1\Admin\General\CurrenciesController;
use App\Http\Controllers\V1\Admin\General\DateFormatsController;
use App\Http\Controllers\V1\Admin\General\GetAllUsedCurrenciesController;
use App\Http\Controllers\V1\Admin\General\NextNumberController;
use App\Http\Controllers\V1\Admin\General\NotesController;
use App\Http\Controllers\V1\Admin\General\NumberPlaceholdersController;
use App\Http\Controllers\V1\Admin\General\SearchController;
use App\Http\Controllers\V1\Admin\General\SearchUsersController;
use App\Http\Controllers\V1\Admin\General\TimeFormatsController;
use App\Http\Controllers\V1\Admin\General\TimezonesController;
use App\Http\Controllers\V1\Admin\Invoice\ChangeInvoiceStatusController;
use App\Http\Controllers\V1\Admin\Invoice\CloneInvoiceController;
use App\Http\Controllers\V1\Admin\Invoice\InvoicesController;
use App\Http\Controllers\V1\Admin\Invoice\InvoiceTemplatesController;
use App\Http\Controllers\V1\Admin\Invoice\SendInvoiceController;
use App\Http\Controllers\V1\Admin\Invoice\SendInvoicePreviewController;
use App\Http\Controllers\V1\Admin\Item\ItemsController;
use App\Http\Controllers\V1\Admin\Item\UnitsController;
use App\Http\Controllers\V1\Admin\Mobile\AuthController;
use App\Http\Controllers\V1\Admin\Modules\ApiTokenController;
use App\Http\Controllers\V1\Admin\Modules\CompleteModuleInstallationController;
use App\Http\Controllers\V1\Admin\Modules\CopyModuleController;
use App\Http\Controllers\V1\Admin\Modules\DisableModuleController;
use App\Http\Controllers\V1\Admin\Modules\DownloadModuleController;
use App\Http\Controllers\V1\Admin\Modules\EnableModuleController;
use App\Http\Controllers\V1\Admin\Modules\ModuleController;
use App\Http\Controllers\V1\Admin\Modules\ModulesController;
use App\Http\Controllers\V1\Admin\Modules\UnzipModuleController;
use App\Http\Controllers\V1\Admin\Modules\UploadModuleController;
use App\Http\Controllers\V1\Admin\Payment\PaymentMethodsController;
use App\Http\Controllers\V1\Admin\Payment\PaymentsController;
use App\Http\Controllers\V1\Admin\Payment\SendPaymentController;
use App\Http\Controllers\V1\Admin\Payment\SendPaymentPreviewController;
use App\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceController;
use App\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceFrequencyController;
use App\Http\Controllers\V1\Admin\Role\AbilitiesController;
use App\Http\Controllers\V1\Admin\Role\RolesController;
use App\Http\Controllers\V1\Admin\Settings\CompanyController;
use App\Http\Controllers\V1\Admin\Settings\CompanyCurrencyCheckTransactionsController;
use App\Http\Controllers\V1\Admin\Settings\DiskController;
use App\Http\Controllers\V1\Admin\Settings\GetCompanyMailConfigurationController;
use App\Http\Controllers\V1\Admin\Settings\GetCompanySettingsController;
use App\Http\Controllers\V1\Admin\Settings\GetSettingsController;
use App\Http\Controllers\V1\Admin\Settings\GetUserSettingsController;
use App\Http\Controllers\V1\Admin\Settings\MailConfigurationController;
use App\Http\Controllers\V1\Admin\Settings\PDFConfigurationController;
use App\Http\Controllers\V1\Admin\Settings\TaxTypesController;
use App\Http\Controllers\V1\Admin\Settings\UpdateCompanySettingsController;
use App\Http\Controllers\V1\Admin\Settings\UpdateSettingsController;
use App\Http\Controllers\V1\Admin\Settings\UpdateUserSettingsController;
use App\Http\Controllers\V1\Admin\Update\CheckVersionController;
use App\Http\Controllers\V1\Admin\Update\CopyFilesController;
use App\Http\Controllers\V1\Admin\Update\DeleteFilesController;
use App\Http\Controllers\V1\Admin\Update\DownloadUpdateController;
use App\Http\Controllers\V1\Admin\Update\FinishUpdateController;
use App\Http\Controllers\V1\Admin\Update\MigrateUpdateController;
use App\Http\Controllers\V1\Admin\Update\UnzipUpdateController;
use App\Http\Controllers\V1\Admin\Users\UsersController;
use App\Http\Controllers\V1\Customer\Auth\ForgotPasswordController as AuthForgotPasswordController;
use App\Http\Controllers\V1\Customer\Auth\ResetPasswordController as AuthResetPasswordController;
use App\Http\Controllers\V1\Customer\Estimate\AcceptEstimateController as CustomerAcceptEstimateController;
use App\Http\Controllers\V1\Customer\Estimate\EstimatesController as CustomerEstimatesController;
use App\Http\Controllers\V1\Customer\Expense\ExpensesController as CustomerExpensesController;
use App\Http\Controllers\V1\Customer\General\BootstrapController as CustomerBootstrapController;
use App\Http\Controllers\V1\Customer\General\DashboardController as CustomerDashboardController;
use App\Http\Controllers\V1\Customer\General\ProfileController as CustomerProfileController;
use App\Http\Controllers\V1\Customer\Invoice\InvoicesController as CustomerInvoicesController;
use App\Http\Controllers\V1\Customer\Payment\PaymentMethodController;
use App\Http\Controllers\V1\Customer\Payment\PaymentsController as CustomerPaymentsController;
use App\Http\Controllers\V1\Installation\AppDomainController;
use App\Http\Controllers\V1\Installation\DatabaseConfigurationController;
use App\Http\Controllers\V1\Installation\FilePermissionsController;
use App\Http\Controllers\V1\Installation\FinishController;
use App\Http\Controllers\V1\Installation\LanguagesController;
use App\Http\Controllers\V1\Installation\LoginController;
use App\Http\Controllers\V1\Installation\OnboardingWizardController;
use App\Http\Controllers\V1\Installation\RequirementsController;
use App\Http\Controllers\V1\Webhook\CronJobController;
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

    Route::middleware(['auth:sanctum', 'company'])->group(function () {
        Route::middleware(['bouncer'])->group(function () {

            // Bootstrap
            // ----------------------------------

            Route::get('/bootstrap', BootstrapController::class);

            // Currencies
            // ----------------------------------

            Route::prefix('/currencies')->group(function () {
                Route::get('/used', GetAllUsedCurrenciesController::class);

                Route::post('/bulk-update-exchange-rate', BulkExchangeRateController::class);
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

            Route::get('/search/user', SearchUsersController::class);

            // MISC
            // ----------------------------------

            Route::get('/config', ConfigController::class);

            Route::get('/currencies', CurrenciesController::class);

            Route::get('/timezones', TimezonesController::class);

            Route::get('/date/formats', DateFormatsController::class);

            Route::get('/time/formats', TimeFormatsController::class);

            Route::get('/next-number', NextNumberController::class);

            Route::get('/number-placeholders', NumberPlaceholdersController::class);

            Route::get('/current-company', AdminCompanyController::class);

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

            Route::get('/invoices/{invoice}/send/preview', SendInvoicePreviewController::class);

            Route::post('/invoices/{invoice}/send', SendInvoiceController::class);

            Route::post('/invoices/{invoice}/clone', CloneInvoiceController::class);

            Route::post('/invoices/{invoice}/status', ChangeInvoiceStatusController::class);

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

            Route::get('/estimates/{estimate}/send/preview', SendEstimatePreviewController::class);

            Route::post('/estimates/{estimate}/send', SendEstimateController::class);

            Route::post('/estimates/{estimate}/clone', CloneEstimateController::class);

            Route::post('/estimates/{estimate}/status', ChangeEstimateStatusController::class);

            Route::post('/estimates/{estimate}/convert-to-invoice', ConvertEstimateController::class);

            Route::get('/estimates/templates', EstimateTemplatesController::class);

            Route::post('/estimates/delete', [EstimatesController::class, 'delete']);

            Route::apiResource('estimates', EstimatesController::class);

            // Expenses
            // ----------------------------------

            Route::get('/expenses/{expense}/show/receipt', ShowReceiptController::class);

            Route::post('/expenses/{expense}/upload/receipts', UploadReceiptController::class);

            Route::post('/expenses/delete', [ExpensesController::class, 'delete']);

            Route::apiResource('expenses', ExpensesController::class);

            Route::apiResource('categories', ExpenseCategoriesController::class);

            // Payments
            // ----------------------------------

            Route::get('/payments/{payment}/send/preview', SendPaymentPreviewController::class);

            Route::post('/payments/{payment}/send', SendPaymentController::class);

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

            Route::get('/currencies/{currency}/exchange-rate', GetExchangeRateController::class);

            Route::get('/currencies/{currency}/active-provider', GetActiveProviderController::class);

            Route::get('/used-currencies', GetUsedCurrenciesController::class);

            Route::get('/supported-currencies', GetSupportedCurrenciesController::class);

            Route::apiResource('exchange-rate-providers', ExchangeRateProviderController::class);

            // Settings
            // ----------------------------------

            Route::get('/me', [CompanyController::class, 'getUser']);

            Route::put('/me', [CompanyController::class, 'updateProfile']);

            Route::get('/me/settings', GetUserSettingsController::class);

            Route::put('/me/settings', UpdateUserSettingsController::class);

            Route::post('/me/upload-avatar', [CompanyController::class, 'uploadAvatar']);

            Route::put('/company', [CompanyController::class, 'updateCompany']);

            Route::post('/company/upload-logo', [CompanyController::class, 'uploadCompanyLogo']);

            Route::get('/company/settings', GetCompanySettingsController::class);

            Route::post('/company/settings', UpdateCompanySettingsController::class);

            Route::get('/settings', GetSettingsController::class);

            Route::post('/settings', UpdateSettingsController::class);

            Route::get('/company/has-transactions', CompanyCurrencyCheckTransactionsController::class);

            // Mails
            // ----------------------------------

            Route::get('/mail/drivers', [MailConfigurationController::class, 'getMailDrivers']);

            Route::get('/mail/config', [MailConfigurationController::class, 'getMailEnvironment']);

            Route::post('/mail/config', [MailConfigurationController::class, 'saveMailEnvironment']);

            Route::post('/mail/test', [MailConfigurationController::class, 'testEmailConfig']);

            Route::get('/company/mail/config', GetCompanyMailConfigurationController::class);

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
            Route::get('/', ModulesController::class);

            Route::get('/check', ApiTokenController::class);

            Route::get('/{module}', ModuleController::class);

            Route::post('/{module}/enable', EnableModuleController::class);

            Route::post('/{module}/disable', DisableModuleController::class);

            Route::post('/download', DownloadModuleController::class);

            Route::post('/upload', UploadModuleController::class);

            Route::post('/unzip', UnzipModuleController::class);

            Route::post('/copy', CopyModuleController::class);

            Route::post('/complete', CompleteModuleInstallationController::class);
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
