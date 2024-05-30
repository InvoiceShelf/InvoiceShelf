<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;

class ShowReceiptController extends Controller
{
    /**
     * Retrieve details of an expense receipt from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Expense $expense)
    {
        $this->authorize('view', $expense);

        if ($expense) {
            $media = $expense->getFirstMedia('receipts');

            if ($media) {
                return response()->file($media->getPath());
            }

            return respondJson('receipt_does_not_exist', 'Receipt does not exist.');
        }
    }
}
