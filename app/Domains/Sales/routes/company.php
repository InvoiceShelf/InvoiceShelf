<?php

use App\Domains\Sales\Http\Controllers\Company\EstimatesController;
use App\Domains\Sales\Http\Controllers\Company\EstimateTemplatesController;
use App\Domains\Sales\Http\Controllers\Company\InvoicesController;
use App\Domains\Sales\Http\Controllers\Company\InvoiceTemplatesController;
use App\Domains\Sales\Http\Controllers\Company\QuotesController;
use App\Domains\Sales\Http\Controllers\Company\QuoteTemplatesController;
use App\Domains\Sales\Http\Controllers\Company\RecurringInvoiceController;
use App\Domains\Sales\Http\Controllers\Company\RecurringInvoiceFrequencyController;
use App\Domains\Sales\Http\Controllers\Company\SerialNumberController;
use Illuminate\Support\Facades\Route;

Route::get('/next-number', [SerialNumberController::class, 'nextNumber']);
Route::get('/number-placeholders', [SerialNumberController::class, 'placeholders']);

Route::get('/invoices/{invoice}/send/preview', [InvoicesController::class, 'sendPreview']);
Route::post('/invoices/{invoice}/send', [InvoicesController::class, 'send']);
Route::post('/invoices/{invoice}/clone', [InvoicesController::class, 'clone']);
Route::post('/invoices/{invoice}/convert-to-estimate', [InvoicesController::class, 'convertToEstimate']);
Route::post('/invoices/{invoice}/credit-note', [InvoicesController::class, 'createCreditNote']);
Route::post('/invoices/{invoice}/status', [InvoicesController::class, 'changeStatus']);

// Two collection-level endpoints that are not resource verbs. Both are
// declared ahead of the resource, so neither literal segment can ever be
// read as an {invoice} key.
Route::prefix('invoices')->group(function (): void {
    Route::post('delete', [InvoicesController::class, 'delete']);
    Route::get('templates', InvoiceTemplatesController::class);
});
Route::apiResources(['invoices' => InvoicesController::class]);

// Recurring templates: first the fixed cron presets the editor offers, then
// the same bulk-delete-before-the-resource ordering.
Route::get('recurring-invoice-frequency', RecurringInvoiceFrequencyController::class);
Route::post('recurring-invoices/delete', [RecurringInvoiceController::class, 'delete']);
Route::apiResources(['recurring-invoices' => RecurringInvoiceController::class]);

Route::get('/estimates/{estimate}/send/preview', [EstimatesController::class, 'sendPreview']);
Route::post('/estimates/{estimate}/send', [EstimatesController::class, 'send']);
Route::post('/estimates/{estimate}/clone', [EstimatesController::class, 'clone']);
Route::post('/estimates/{estimate}/status', [EstimatesController::class, 'changeStatus']);
Route::post('/estimates/{estimate}/convert-to-invoice', [EstimatesController::class, 'convertToInvoice']);

// As for invoices, ahead of the resource — here the templates listing comes
// first, which is the order this file has always used for offers.
Route::prefix('estimates')->group(function (): void {
    Route::get('templates', EstimateTemplatesController::class);
    Route::post('delete', [EstimatesController::class, 'delete']);
});
Route::apiResources(['estimates' => EstimatesController::class]);

Route::get('/quotes/{quote}/send/preview', [QuotesController::class, 'sendPreview']);
Route::post('/quotes/{quote}/send', [QuotesController::class, 'send']);
Route::post('/quotes/{quote}/clone', [QuotesController::class, 'clone']);
Route::post('/quotes/{quote}/status', [QuotesController::class, 'changeStatus']);
Route::post('/quotes/{quote}/convert-to-invoice', [QuotesController::class, 'convertToInvoice']);

// As for invoices, ahead of the resource — here the templates listing comes
// first, which is the order this file has always used for offers.
Route::prefix('quotes')->group(function (): void {
    Route::get('templates', QuoteTemplatesController::class);
    Route::post('delete', [QuotesController::class, 'delete']);
});
Route::apiResources(['quotes' => QuotesController::class]);
