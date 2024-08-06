<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Currency;
use Illuminate\Http\Request;

class GetCompanyCurrencyController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $currency_id = CompanySetting::getSetting('currency', $request->header('company'));

        $currency = Currency::find($currency_id);

        return response()->json($currency);
    }
}
