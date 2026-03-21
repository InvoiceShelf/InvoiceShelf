<?php

namespace App\Http\Controllers\V1\Admin\ExchangeRate;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRateProvider;
use App\Traits\ExchangeRateProvidersTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetSupportedCurrenciesController extends Controller
{
    use ExchangeRateProvidersTrait;

    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        return $this->getSupportedCurrencies($request);
    }
}
