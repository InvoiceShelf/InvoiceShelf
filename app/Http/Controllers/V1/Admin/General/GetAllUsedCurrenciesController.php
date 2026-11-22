<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetAllUsedCurrenciesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $companyId = $request->header('company');

        $invoices = Invoice::where('company_id', $companyId)->whereNull('exchange_rate')->pluck('currency_id')->toArray();

        $taxes = Tax::where('company_id', $companyId)->whereNull('exchange_rate')->pluck('currency_id')->toArray();

        $estimates = Estimate::where('company_id', $companyId)->whereNull('exchange_rate')->pluck('currency_id')->toArray();

        $payments = Payment::where('company_id', $companyId)->whereNull('exchange_rate')->pluck('currency_id')->toArray();

        $currencies = array_merge($invoices, $taxes, $estimates, $payments);

        return response()->json([
            'currencies' => Currency::whereIn('id', $currencies)->get(),
        ]);
    }
}
