<?php

namespace App\Services;

use App\Http\Requests\ExchangeRateProviderRequest;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\ExchangeRateProvider;
use App\Services\ExchangeRate\ExchangeRateDriverFactory;
use App\Services\ExchangeRate\ExchangeRateException;

class ExchangeRateProviderService
{
    public function create(ExchangeRateProviderRequest $request): ExchangeRateProvider
    {
        return ExchangeRateProvider::create($request->getExchangeRateProviderPayload());
    }

    public function update(ExchangeRateProvider $provider, ExchangeRateProviderRequest $request): ExchangeRateProvider
    {
        $provider->update($request->getExchangeRateProviderPayload());

        return $provider;
    }

    public function checkActiveCurrencies($request)
    {
        $currencies = $request->currencies;

        if (empty($currencies)) {
            return collect();
        }

        $query = ExchangeRateProvider::where('active', true);

        foreach ($currencies as $currency) {
            $query->orWhere(function ($q) use ($currency) {
                $q->where('active', true)
                    ->whereJsonContains('currencies', $currency);
            });
        }

        return $query->get();
    }

    public function checkUpdateActiveCurrencies(ExchangeRateProvider $provider, $request)
    {
        $currencies = $request->currencies;

        if (empty($currencies)) {
            return collect();
        }

        $query = ExchangeRateProvider::where('id', '<>', $provider->id)
            ->where('active', true);

        $query->where(function ($q) use ($currencies) {
            foreach ($currencies as $currency) {
                $q->orWhereJsonContains('currencies', $currency);
            }
        });

        return $query->get();
    }

    public function checkProviderStatus($request)
    {
        try {
            $driver = ExchangeRateDriverFactory::make(
                $request['driver'],
                $request['key'],
                $request['driver_config'] ?? []
            );

            $rates = $driver->validateConnection();

            return response()->json([
                'exchangeRate' => $rates,
            ], 200);
        } catch (ExchangeRateException $e) {
            return respondJson($e->errorKey, $e->getMessage());
        }
    }

    public function getExchangeRate(string $driver, string $apiKey, array $driverConfig, string $baseCurrency, string $targetCurrency)
    {
        try {
            $driverInstance = ExchangeRateDriverFactory::make($driver, $apiKey, $driverConfig);

            return response()->json([
                'exchangeRate' => $driverInstance->getExchangeRate($baseCurrency, $targetCurrency),
            ], 200);
        } catch (ExchangeRateException $e) {
            return respondJson($e->errorKey, $e->getMessage());
        }
    }

    public function getSupportedCurrencies(string $driver, string $apiKey, array $driverConfig = [])
    {
        try {
            $driverInstance = ExchangeRateDriverFactory::make($driver, $apiKey, $driverConfig);

            return response()->json([
                'supportedCurrencies' => $driverInstance->getSupportedCurrencies(),
            ]);
        } catch (ExchangeRateException $e) {
            return respondJson($e->errorKey, $e->getMessage());
        }
    }

    public function addExchangeRateLog($model): ExchangeRateLog
    {
        return ExchangeRateLog::create([
            'exchange_rate' => $model->exchange_rate,
            'company_id' => $model->company_id,
            'base_currency_id' => $model->currency_id,
            'currency_id' => CompanySetting::getSetting('currency', $model->company_id),
        ]);
    }
}
