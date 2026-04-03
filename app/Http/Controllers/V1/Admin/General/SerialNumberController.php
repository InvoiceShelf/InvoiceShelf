<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\SerialNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SerialNumberController extends Controller
{
    public function nextNumber(Request $request, Invoice $invoice, Estimate $estimate, Payment $payment): JsonResponse
    {
        $key = $request->key;
        $nextNumber = null;
        $serial = (new SerialNumberService)
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
                    return response()->json([
                        'success' => false,
                    ]);
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

    public function placeholders(Request $request): JsonResponse
    {
        if ($request->format) {
            $placeholders = SerialNumberService::getPlaceholders($request->format);
        } else {
            $placeholders = [];
        }

        return response()->json([
            'success' => true,
            'placeholders' => $placeholders,
        ]);
    }
}
