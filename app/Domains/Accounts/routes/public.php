<?php

use App\Domains\Accounts\Http\Controllers\Auth\AuthController;
use App\Domains\Accounts\Http\Controllers\Auth\ForgotPasswordController;
use App\Domains\Accounts\Http\Controllers\Auth\InvitationRegistrationController;
use App\Domains\Accounts\Http\Controllers\Auth\ResetPasswordController;
use App\Domains\Accounts\Http\Controllers\Auth\SsoHandoffController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    // Ten attempts a minute: password guessing, not traffic. The third
    // argument prefixes the counter: without it this shares a key with the
    // API group's own limiter and both would count the same request.
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum']); // needs the token it revokes
    Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:10,2');
    Route::post('/reset/password', [ResetPasswordController::class, 'reset']); // the mailed token travels in the body

    // Single sign-on for the mobile app (SsoHandoffController). Starting one
    // is throttled like the password sign-in. Trading the code has a looser
    // limit of its own, so starting others cannot throw away a finished
    // sign-in: each try at a code ends its hand-off anyway.
    Route::post('sso/handoffs', [SsoHandoffController::class, 'store'])->middleware('throttle:10,1,sso-handoff');
    Route::post('sso/handoffs/{handoff}/token', [SsoHandoffController::class, 'token'])->middleware('throttle:30,1,sso-handoff-token');
});

Route::get('/invitations/{token}/details', [InvitationRegistrationController::class, 'details']);
Route::post('/auth/register-with-invitation', [InvitationRegistrationController::class, 'register']);
