<?php

use App\Platform\Mail\Http\Admin\MailConfigurationController;
use Illuminate\Support\Facades\Route;

// The server's own mail transport (super administrator). Locked on a managed
// install, where the provider sets it; company mail settings stay open.
Route::controller(MailConfigurationController::class)->group(function () {
    Route::get('/mail/drivers', 'getMailDrivers');
    Route::get('/mail/config', 'getMailEnvironment');
    Route::post('/mail/config', 'saveMailEnvironment');
    Route::post('/mail/test', 'testEmailConfig');
});
