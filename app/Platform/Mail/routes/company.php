<?php

use App\Platform\Mail\Http\Company\CompanyMailConfigurationController;
use Illuminate\Support\Facades\Route;

Route::get('/company/mail/drivers', [CompanyMailConfigurationController::class, 'getDrivers']);
Route::get('/company/mail/config', [CompanyMailConfigurationController::class, 'getDefaultConfig']);
Route::get('/company/mail/company-config', [CompanyMailConfigurationController::class, 'getMailConfig']);
Route::post('/company/mail/company-config', [CompanyMailConfigurationController::class, 'saveMailConfig']);
Route::post('/company/mail/company-test', [CompanyMailConfigurationController::class, 'testMailConfig']);
