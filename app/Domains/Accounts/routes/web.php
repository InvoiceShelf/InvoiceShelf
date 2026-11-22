<?php

use App\Domains\Accounts\Http\Controllers\Auth\LoginController;
use App\Domains\Accounts\Http\Controllers\Auth\OidcController;
use App\Domains\Accounts\Http\Controllers\Auth\SsoController;
use App\Domains\Accounts\Http\Controllers\InvitationDeclineController;
use Illuminate\Support\Facades\Route;

Route::post('login', [LoginController::class, 'login']);
Route::post('auth/logout', [LoginController::class, 'logout']);
Route::get('/invitations/{token}/decline', InvitationDeclineController::class);

// Single sign-on, off until configured. SsoController starts a sign-in, from
// the login page or from the browser the mobile app opens for a hand-off,
// and OidcController takes the identity provider's answer. Only the web
// flow's start is for guests: the mobile app's hand-off may finish in a
// browser that is signed in to the web app already.
//
// Each route counts against a limit of its own (the third throttle
// argument), and the callback's is the loosest: a sign-in that made it to
// the identity provider and back must not be thrown away because the same
// address started a few others.
Route::prefix('auth/sso')->group(function (): void {
    Route::get('redirect', [SsoController::class, 'redirect'])->middleware(['guest', 'throttle:20,1,sso-redirect'])->name('sso.redirect');
    Route::get('app/{handoff}', [SsoController::class, 'app'])->middleware('throttle:20,1,sso-app')->name('sso.app');
    Route::post('app/{handoff}', [SsoController::class, 'continueApp'])->middleware('throttle:20,1,sso-app-continue')->name('sso.app.continue');
    Route::delete('app/{handoff}', [SsoController::class, 'cancelApp'])->middleware('throttle:20,1,sso-app-cancel')->name('sso.app.cancel');
});

Route::get('auth/oidc/callback', [OidcController::class, 'callback'])->middleware('throttle:60,1,sso-callback')->name('oidc.callback');
