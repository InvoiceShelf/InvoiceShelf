<?php

namespace InvoiceShelf\Http\Controllers\V1\Admin\Expense;

use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Http\Requests\UploadExpenseReceiptRequest;
use InvoiceShelf\Models\Expense;

class UploadReceiptController extends Controller
{
    /**
     * Upload the expense receipts to storage.
     *
     * @param  \InvoiceShelf\Http\Requests\ExpenseRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(UploadExpenseReceiptRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $data = json_decode($request->attachment_receipt);

        if ($data) {
            if ($request->type === 'edit') {
                $expense->clearMediaCollection('receipts');
            }

            $expense->addMediaFromBase64($data->data)
                ->usingFileName($data->name)
                ->toMediaCollection('receipts');
        }

        return response()->json([
            'success' => 'Expense receipts uploaded successfully',
        ], 200);
    }
}
