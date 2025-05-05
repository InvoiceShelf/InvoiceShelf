<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\SerialNumberFormatter;
use Illuminate\Http\Request;

class NextNumberController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Invoice $invoice, Estimate $estimate, Payment $payment)
    {
        $key = $request->key;
        $nextNumber = null;
        $serial = (new SerialNumberFormatter)
            ->setCompany($request->header('company'))
            ->setCustomer($request->userId);

        try {
            switch ($key) {
                case 'invoice':
                    $nextNumber = $serial->setModel($invoice)
                        ->setModelObject($request->model_id)
                        ->getNextNumber();

                    break;

                case 'estimate':
                    $nextNumber = $serial->setModel($estimate)
                        ->setModelObject($request->model_id)
                        ->getNextNumber();

                    break;

                case 'payment':
                    $nextNumber = $serial->setModel($payment)
                        ->setModelObject($request->model_id)
                        ->getNextNumber();

                    break;

                default:
                    return;
            }
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'nextNumber' => $nextNumber,
        ]);
    }
}
