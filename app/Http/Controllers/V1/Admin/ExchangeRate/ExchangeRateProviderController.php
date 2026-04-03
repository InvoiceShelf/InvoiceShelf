<?php

namespace App\Http\Controllers\V1\Admin\ExchangeRate;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRateProviderRequest;
use App\Http\Resources\ExchangeRateProviderResource;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\ExchangeRateLog;
use App\Models\ExchangeRateProvider;
use App\Traits\ExchangeRateProvidersTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ExchangeRateProviderController extends Controller
{
    use ExchangeRateProvidersTrait;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $exchangeRateProviders = ExchangeRateProvider::whereCompany()->paginate($limit);

        return ExchangeRateProviderResource::collection($exchangeRateProviders);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(ExchangeRateProviderRequest $request)
    {
        $this->authorize('create', ExchangeRateProvider::class);

        $query = ExchangeRateProvider::checkActiveCurrencies($request);

        if (count($query) !== 0) {
            return respondJson('currency_used', 'Currency used.');
        }

        $checkConverterApi = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

        if ($checkConverterApi->status() == 200) {
            $exchangeRateProvider = ExchangeRateProvider::createFromRequest($request);

            return new ExchangeRateProviderResource($exchangeRateProvider);
        }

        return $checkConverterApi;
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('view', $exchangeRateProvider);

        return new ExchangeRateProviderResource($exchangeRateProvider);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(ExchangeRateProviderRequest $request, ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('update', $exchangeRateProvider);

        $query = $exchangeRateProvider->checkUpdateActiveCurrencies($request);

        if (count($query) !== 0) {
            return respondJson('currency_used', 'Currency used.');
        }

        $checkConverterApi = ExchangeRateProvider::checkExchangeRateProviderStatus($request);

        if ($checkConverterApi->status() == 200) {
            $exchangeRateProvider->updateFromRequest($request);

            return new ExchangeRateProviderResource($exchangeRateProvider);
        }

        return $checkConverterApi;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('delete', $exchangeRateProvider);

        if ($exchangeRateProvider->active == true) {
            return respondJson('provider_active', 'Provider Active.');
        }

        $exchangeRateProvider->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function activeProvider(Request $request, Currency $currency)
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

    public function getRate(Request $request, Currency $currency)
    {
        $settings = CompanySetting::getSettings(['currency'], $request->header('company'));
        $baseCurrency = Currency::findOrFail($settings['currency']);

        $query = ExchangeRateProvider::whereJsonContains('currencies', $currency->code)
            ->where('active', true)
            ->get()
            ->toArray();

        $exchange_rate = ExchangeRateLog::where('base_currency_id', $currency->id)
            ->where('currency_id', $baseCurrency->id)
            ->orderBy('created_at', 'desc')
            ->value('exchange_rate');

        if ($query) {
            $filter = Arr::only($query[0], ['key', 'driver', 'driver_config']);
            $exchange_rate_value = $this->getExchangeRate($filter, $currency->code, $baseCurrency->code);

            if ($exchange_rate_value->status() == 200) {
                return $exchange_rate_value;
            }
        }
        if ($exchange_rate) {
            return response()->json([
                'exchangeRate' => [$exchange_rate],
            ], 200);
        }

        return response()->json([
            'error' => 'no_exchange_rate_available',
        ], 200);
    }

    public function supportedCurrencies(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        return $this->getSupportedCurrencies($request);
    }

    public function usedCurrencies(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        $providerId = $request->provider_id;

        $activeExchangeRateProviders = ExchangeRateProvider::where('active', true)
            ->whereCompany()
            ->when($providerId, function ($query) use ($providerId) {
                return $query->where('id', '<>', $providerId);
            })
            ->pluck('currencies');
        $activeExchangeRateProvider = [];

        foreach ($activeExchangeRateProviders as $data) {
            if (is_array($data)) {
                for ($limit = 0; $limit < count($data); $limit++) {
                    $activeExchangeRateProvider[] = $data[$limit];
                }
            }
        }

        $allExchangeRateProviders = ExchangeRateProvider::whereCompany()->pluck('currencies');
        $allExchangeRateProvider = [];

        foreach ($allExchangeRateProviders as $data) {
            if (is_array($data)) {
                for ($limit = 0; $limit < count($data); $limit++) {
                    $allExchangeRateProvider[] = $data[$limit];
                }
            }
        }

        return response()->json([
            'allUsedCurrencies' => $allExchangeRateProvider ? $allExchangeRateProvider : [],
            'activeUsedCurrencies' => $activeExchangeRateProvider ? $activeExchangeRateProvider : [],
        ]);
    }
}
