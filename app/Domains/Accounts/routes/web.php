<?php

use App\Domains\Accounts\Http\Controllers\Auth\LoginController;
use App\Domains\Accounts\Http\Controllers\Auth\OAuth2Controller;
use App\Domains\Accounts\Http\Controllers\InvitationDeclineController;
use Illuminate\Support\Facades\Route;

Route::post('login', [LoginController::class, 'login']);
Route::post('auth/logout', [LoginController::class, 'logout']);
Route::get('/invitations/{token}/decline', InvitationDeclineController::class);

// Sign-in with SSO, off until configured (OAuth2Controller). Only the web
// flow's start is for guests: the mobile app's hand-off may finish in a
// browser that is signed in to the web app already.
Route::prefix('auth/oauth2')->middleware('throttle:30,1,oauth2')->group(function (): void {
    Route::get('redirect', [OAuth2Controller::class, 'redirect'])->middleware('guest')->name('oauth2.redirect');
    Route::get('app/{handoff}', [OAuth2Controller::class, 'app'])->name('oauth2.app');
    Route::get('callback', [OAuth2Controller::class, 'callback'])->name('oauth2.callback');
});
