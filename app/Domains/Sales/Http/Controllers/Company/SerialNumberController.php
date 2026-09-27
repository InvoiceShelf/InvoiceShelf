<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Application\SerialNumberService;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\Quote;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves the number-format previews the settings and document screens use.
 */
class SerialNumberController extends Controller
{
    /**
     * Render the number the next document of the requested kind would carry.
     *
     * The `key` query parameter picks the document kind; an unknown one is
     * reported as a plain failure rather than an error. `format` overrides the
     * company's stored format so the settings screen can preview edits, and
     * `model_id` lets an existing document keep the numbers it already has.
     */
    public function nextNumber(Request $request, Invoice $invoice, Estimate $estimate, Payment $payment): JsonResponse
    {
        $serial = (new SerialNumberService)
            ->setCompany($request->header('company'))
            ->setCustomer($request->userId);

        // Invoices and credit notes live in one table, so each is pinned to its
        // own row type: the preview must never count the other kind's rows.
        switch ($request->key) {
            case 'invoice':
                $serial->setModel($invoice)
                    ->setSequenceScope(['type' => Invoice::TYPE_INVOICE]);

                break;

            case 'credit_note':
                $serial->setModel($invoice)
                    ->setSettingKey('credit_note_number_format')
                    ->setSequenceScope(['type' => Invoice::TYPE_CREDIT_NOTE]);

                break;

            case 'estimate':
                $serial->setModel($estimate);

                break;

            case 'quote':
                $serial->setModel(new Quote);
                break;

            case 'payment':
                $serial->setModel($payment);

                break;

            default:
                return response()->json([
                    'success' => false,
                ]);
        }

        try {
            $nextNumber = $serial->setModelObject($request->model_id)
                ->getNextNumber($request->input('format'));
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

    /**
     * List the tokens a submitted format string is made of.
     */
    public function placeholders(Request $request): JsonResponse
    {
        $format = $request->input('format');

        return response()->json([
            'success' => true,
            'placeholders' => $format ? SerialNumberService::getPlaceholders($format) : [],
        ]);
    }
}
