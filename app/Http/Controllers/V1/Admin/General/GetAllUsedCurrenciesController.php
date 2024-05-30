<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tax;
use Illuminate\Http\Request;

class GetAllUsedCurrenciesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $invoices = Invoice::where('exchange_rate', null)->pluck('currency_id')->toArray();

        $taxes = Tax::where('exchange_rate', null)->pluck('currency_id')->toArray();

        $estimates = Estimate::where('exchange_rate', null)->pluck('currency_id')->toArray();

        $payments = Payment::where('exchange_rate', null)->pluck('currency_id')->toArray();

        $currencies = array_merge($invoices, $taxes, $estimates, $payments);

        return response()->json([
            'currencies' => Currency::whereIn('id', $currencies)->get(),
        ]);
    }
}
