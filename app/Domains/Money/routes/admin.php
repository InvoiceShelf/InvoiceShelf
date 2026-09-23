<?php

use App\Domains\Money\Http\Controllers\Admin\CurrenciesController;
use Illuminate\Support\Facades\Route;

Route::get('currencies', [CurrenciesController::class, 'index']);
Route::post('currencies/refresh', [CurrenciesController::class, 'refresh']);
