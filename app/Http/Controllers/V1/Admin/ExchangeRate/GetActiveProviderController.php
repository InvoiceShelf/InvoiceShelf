<?php

namespace App\Http\Controllers\V1\Admin\ExchangeRate;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\ExchangeRateProvider;
use Illuminate\Http\Request;

class GetActiveProviderController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Currency $currency)
    {
        $query = ExchangeRateProvider::whereCompany()->whereJsonContains('currencies', $currency->code)
            ->where('active', true)
            ->get();

        if (count($query) !== 0) {
            return response()->json([
                'success' => true,
                'message' => 'provider_active',
            ], 200);
        }

        return response()->json([
            'error' => 'no_active_provider',
        ], 200);
    }
}
