<?php

use App\Domains\Purchases\Http\Controllers\Company\BillsController;
use App\Domains\Purchases\Http\Controllers\Company\ExpenseCategoriesController;
use App\Domains\Purchases\Http\Controllers\Company\ExpensesController;
use App\Domains\Purchases\Http\Controllers\Company\PurchaseAttachmentsController;
use App\Domains\Purchases\Http\Controllers\Company\PurchaseOptionsController;
use App\Domains\Purchases\Http\Controllers\Company\PurchasesReportController;
use App\Domains\Purchases\Http\Controllers\Company\RecurringCostsController;
use App\Domains\Purchases\Http\Controllers\Company\SupplierCreditsController;
use App\Domains\Purchases\Http\Controllers\Company\SupplierPaymentsController;
use App\Domains\Purchases\Http\Controllers\Company\SupplierRefundsController;
use App\Domains\Purchases\Http\Controllers\Company\SuppliersController;
use Illuminate\Support\Facades\Route;

Route::get('/expenses/{expense}/show/receipt', [ExpensesController::class, 'showReceipt']);
Route::post('/expenses/{expense}/upload/receipts', [ExpensesController::class, 'uploadReceipt']);
Route::match(['POST'], 'expenses/delete', [ExpensesController::class, 'delete']);
Route::resource('expenses', ExpensesController::class)->except(['create', 'edit']);
Route::resource('categories', ExpenseCategoriesController::class)->except(['create', 'edit']);

Route::apiResource('suppliers', SuppliersController::class)->parameters(['suppliers' => 'supplier'])->only(['index', 'show', 'store', 'update']);
Route::post('bills/{bill}/actions', [BillsController::class, 'action']);
Route::apiResource('bills', BillsController::class)->parameters(['bills' => 'bill'])->only(['index', 'show', 'store', 'update']);
Route::post('supplier-payments/{supplierPayment}/actions', [SupplierPaymentsController::class, 'action']);
Route::put('supplier-payments/{supplierPayment}/allocations', [SupplierPaymentsController::class, 'allocations']);
Route::apiResource('supplier-payments', SupplierPaymentsController::class)->parameters(['supplier-payments' => 'supplierPayment'])->only(['index', 'show', 'store']);
Route::post('supplier-credits/{supplierCredit}/actions', [SupplierCreditsController::class, 'action']);
Route::put('supplier-credits/{supplierCredit}/allocations', [SupplierCreditsController::class, 'allocations']);
Route::apiResource('supplier-credits', SupplierCreditsController::class)->parameters(['supplier-credits' => 'supplierCredit'])->only(['index', 'show', 'store']);
Route::post('supplier-refunds/{supplierRefund}/actions', [SupplierRefundsController::class, 'action']);
Route::apiResource('supplier-refunds', SupplierRefundsController::class)->parameters(['supplier-refunds' => 'supplierRefund'])->only(['index', 'show', 'store']);
Route::post('recurring-costs/{recurringCost}/actions', [RecurringCostsController::class, 'action']);
Route::apiResource('recurring-costs', RecurringCostsController::class)->parameters(['recurring-costs' => 'recurringCost'])->only(['index', 'show', 'store', 'update']);
Route::post('{kind}/{record}/attachments', [PurchaseAttachmentsController::class, 'store'])->whereIn('kind', ['bills', 'supplier-credits'])->whereNumber('record');
Route::get('{kind}/{record}/attachments/{attachment}', [PurchaseAttachmentsController::class, 'show'])->whereIn('kind', ['bills', 'supplier-credits'])->whereNumber(['record', 'attachment']);
Route::get('reports/purchases', PurchasesReportController::class);
Route::get('purchase-options', PurchaseOptionsController::class);
