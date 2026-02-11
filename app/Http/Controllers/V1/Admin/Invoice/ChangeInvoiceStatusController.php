<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class ChangeInvoiceStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $resetPayments = $request->boolean('reset_payments', false);
        $paidStatus = $request->input('paid_status');
        $status = $request->input('status');

        if ($resetPayments) {
            $invoice->payments()->delete();
        }

        if ($paidStatus) {
            if ($paidStatus === Invoice::STATUS_PAID) {
                $invoice->paid_status = Invoice::STATUS_PAID;
                $invoice->due_amount = 0;
                $invoice->base_due_amount = 0;
                $invoice->status = Invoice::STATUS_COMPLETED;
            } elseif ($paidStatus === Invoice::STATUS_UNPAID) {
                $invoice->paid_status = Invoice::STATUS_UNPAID;
                $invoice->due_amount = $invoice->total;
                $invoice->base_due_amount = $invoice->due_amount * $invoice->exchange_rate;
                $invoice->status = $invoice->getPreviousStatus();
            } elseif ($paidStatus === Invoice::STATUS_PARTIALLY_PAID) {
                $paidAmount = (int) $request->input('paid_amount', 0);

                if ($paidAmount < 0) {
                    $paidAmount = 0;
                }

                if ($paidAmount > $invoice->total) {
                    $paidAmount = $invoice->total;
                }

                $invoice->due_amount = $invoice->total - $paidAmount;
                $invoice->base_due_amount = $invoice->due_amount * $invoice->exchange_rate;

                if ($invoice->due_amount === 0) {
                    $invoice->paid_status = Invoice::STATUS_PAID;
                    $invoice->status = Invoice::STATUS_COMPLETED;
                } elseif ($invoice->due_amount === $invoice->total) {
                    $invoice->paid_status = Invoice::STATUS_UNPAID;
                    $invoice->status = $invoice->getPreviousStatus();
                } else {
                    $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
                    $invoice->status = $invoice->getPreviousStatus();
                }
            }
        } elseif ($status) {
            if ($status === Invoice::STATUS_DRAFT) {
                $invoice->status = Invoice::STATUS_DRAFT;
                $invoice->sent = false;
                $invoice->viewed = false;
            } elseif ($status === Invoice::STATUS_SENT) {
                $invoice->status = Invoice::STATUS_SENT;
                $invoice->sent = true;
                $invoice->viewed = false;
            } elseif ($status === Invoice::STATUS_VIEWED) {
                $invoice->status = Invoice::STATUS_VIEWED;
                $invoice->sent = true;
                $invoice->viewed = true;
            } elseif ($status === Invoice::STATUS_COMPLETED) {
                $invoice->status = Invoice::STATUS_COMPLETED;
                $invoice->paid_status = Invoice::STATUS_PAID;
                $invoice->due_amount = 0;
                $invoice->base_due_amount = 0;
            }
        }

        $invoice->save();

        return response()->json([
            'success' => true,
        ]);
    }
}
