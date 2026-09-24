<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkExchangeRateRequest;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tax;
use App\Support\MoneyConversion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BulkExchangeRateController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
     */
    public function __invoke(BulkExchangeRateRequest $request)
    {
        $bulkExchangeRate = CompanySetting::getSetting('bulk_exchange_rate_configured', $request->header('company'));

        if ($bulkExchangeRate == 'NO') {
            if ($request->currencies) {
                foreach ($request->currencies as $currency) {
                    $currency['exchange_rate'] = $currency['exchange_rate'] ?? 1;

                    $invoices = Invoice::where('currency_id', $currency['id'])->get();

                    if ($invoices) {
                        foreach ($invoices as $invoice) {
                            $invoice->update([
                                'exchange_rate' => $currency['exchange_rate'],
                                'base_discount_val' => MoneyConversion::toBaseMinor($invoice->discount_val, $currency['exchange_rate']),
                                'base_sub_total' => MoneyConversion::toBaseMinor($invoice->sub_total, $currency['exchange_rate']),
                                'base_total' => MoneyConversion::toBaseMinor($invoice->total, $currency['exchange_rate']),
                                'base_tax' => MoneyConversion::toBaseMinor($invoice->tax, $currency['exchange_rate']),
                                'base_due_amount' => MoneyConversion::toBaseMinor($invoice->due_amount, $currency['exchange_rate']),
                            ]);

                            $this->items($invoice);
                        }
                    }

                    $estimates = Estimate::where('currency_id', $currency['id'])->get();

                    if ($estimates) {
                        foreach ($estimates as $estimate) {
                            $estimate->update([
                                'exchange_rate' => $currency['exchange_rate'],
                                'base_discount_val' => MoneyConversion::toBaseMinor($estimate->discount_val, $currency['exchange_rate']),
                                'base_sub_total' => MoneyConversion::toBaseMinor($estimate->sub_total, $currency['exchange_rate']),
                                'base_total' => MoneyConversion::toBaseMinor($estimate->total, $currency['exchange_rate']),
                                'base_tax' => MoneyConversion::toBaseMinor($estimate->tax, $currency['exchange_rate']),
                            ]);

                            $this->items($estimate);
                        }
                    }

                    $taxes = Tax::where('currency_id', $currency['id'])->get();

                    if ($taxes) {
                        foreach ($taxes as $tax) {
                            $tax->exchange_rate = $currency['exchange_rate'];
                            $tax->base_amount = MoneyConversion::toBaseMinor($tax->amount, $currency['exchange_rate']);
                            $tax->save();
                        }
                    }

                    $payments = Payment::where('currency_id', $currency['id'])->get();

                    if ($payments) {
                        foreach ($payments as $payment) {
                            $payment->exchange_rate = $currency['exchange_rate'];
                            $payment->base_amount = MoneyConversion::toBaseMinor($payment->amount, $currency['exchange_rate']);
                            $payment->save();
                        }
                    }
                }
            }

            $settings = [
                'bulk_exchange_rate_configured' => 'YES',
            ];

            CompanySetting::setSettings($settings, $request->header('company'));

            return response()->json([
                'success' => true,
            ]);
        }

        return response()->json([
            'error' => false,
        ]);
    }

    public function items($model)
    {
        foreach ($model->items as $item) {
            $item->update([
                'exchange_rate' => $model->exchange_rate,
                'base_discount_val' => MoneyConversion::toBaseMinor($item->discount_val, $model->exchange_rate),
                'base_price' => MoneyConversion::toBaseMinor($item->price, $model->exchange_rate),
                'base_tax' => MoneyConversion::toBaseMinor($item->tax, $model->exchange_rate),
                'base_total' => MoneyConversion::toBaseMinor($item->total, $model->exchange_rate),
            ]);

            $this->taxes($item);
        }

        $this->taxes($model);
    }

    public function taxes($model)
    {
        if ($model->taxes()->exists()) {
            $model->taxes->map(function ($tax) use ($model) {
                $tax->update([
                    'exchange_rate' => $model->exchange_rate,
                    'base_amount' => MoneyConversion::toBaseMinor($tax->amount, $model->exchange_rate),
                ]);
            });
        }
    }
}
