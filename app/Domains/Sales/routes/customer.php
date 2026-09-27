<?php

use App\Domains\Sales\Http\Controllers\CustomerPortal\AcceptEstimateController;
use App\Domains\Sales\Http\Controllers\CustomerPortal\AcceptQuoteController;
use App\Domains\Sales\Http\Controllers\CustomerPortal\EstimatesController;
use App\Domains\Sales\Http\Controllers\CustomerPortal\InvoicesController;
use App\Domains\Sales\Http\Controllers\CustomerPortal\QuotesController;
use Illuminate\Support\Facades\Route;

Route::get('invoices', [InvoicesController::class, 'index']);
Route::get('invoices/{id}', [InvoicesController::class, 'show']);
Route::post('/estimate/{estimate}/status', AcceptEstimateController::class);
Route::get('estimates', [EstimatesController::class, 'index']);
Route::get('estimates/{id}', [EstimatesController::class, 'show']);

Route::post('/quote/{quote}/status', AcceptQuoteController::class);
Route::get('quotes', [QuotesController::class, 'index']);
Route::get('quotes/{id}', [QuotesController::class, 'show']);
