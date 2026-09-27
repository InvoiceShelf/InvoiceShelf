<?php

use App\Domains\Sales\Http\Controllers\DocumentPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices/pdf/{invoice:unique_hash}', [DocumentPdfController::class, 'invoice']);
Route::get('/estimates/pdf/{estimate:unique_hash}', [DocumentPdfController::class, 'estimate']);

Route::get('/quotes/pdf/{quote:unique_hash}', [DocumentPdfController::class, 'quote']);
