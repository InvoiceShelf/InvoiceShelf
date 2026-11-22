<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Silber\Bouncer\BouncerFacade;

class ShowReceiptController extends Controller
{
    /**
     * Retrieve details of an expense receipt from storage.
     *
     * @return JsonResponse
     */
    public function __invoke(Expense $expense)
    {
        // Under /reports the link carries no `company` header, so the expense's company is the
        // one to answer for. The API route names a company, and keeps it.
        if (BouncerFacade::scope()->get() === null) {
            BouncerFacade::scope()->to($expense->company_id);
        }

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
