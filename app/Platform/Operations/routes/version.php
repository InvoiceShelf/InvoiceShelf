<?php

use App\Platform\Operations\Http\AppVersionController;
use App\Platform\Operations\Http\ClientManifestController;
use Illuminate\Support\Facades\Route;

// Build probe. Unauthenticated on purpose: the SPA shell and the updater both
// read it before any session or company context exists.
Route::get('app/version', AppVersionController::class);

// The thin clients' equivalent of the Blade shell, read before sign-in.
Route::get('app/client-manifest', ClientManifestController::class);
