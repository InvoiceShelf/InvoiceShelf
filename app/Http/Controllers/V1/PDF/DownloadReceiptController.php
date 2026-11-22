<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class DownloadReceiptController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  string  $hash
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Expense $expense)
    {
        // The link carries no `company` header, so the expense's company is the one to answer for.
        BouncerFacade::scope()->to($expense->company_id);

        $this->authorize('view', $expense);

        if ($expense) {
            $media = $expense->getFirstMedia('receipts');
            if ($media) {
                $imagePath = $media->getPath();
                $response = \Response::download($imagePath, $media->file_name);
                if (ob_get_contents()) {
                    ob_end_clean();
                }

                return $response;
            }
        }

        return response()->json([
            'error' => 'receipt_not_found',
        ]);
    }
}
