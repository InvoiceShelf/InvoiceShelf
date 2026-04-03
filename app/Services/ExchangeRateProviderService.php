<?php

namespace App\Services;

use App\Http\Requests\ExchangeRateProviderRequest;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\ExchangeRateProvider;
use Illuminate\Support\Facades\Http;

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
        return ExchangeRateProvider::whereJsonContains('currencies', $request->currencies)
            ->where('active', true)
            ->get();
    }

    public function checkUpdateActiveCurrencies(ExchangeRateProvider $provider, $request)
    {
        return ExchangeRateProvider::where('active', $request->active)
            ->where('id', '<>', $provider->id)
            ->whereJsonContains('currencies', $request->currencies)
            ->get();
    }

    public function checkProviderStatus($request)
    {
        switch ($request['driver']) {
            case 'currency_freak':
                $url = 'https://api.currencyfreaks.com/latest?apikey='.$request['key'].'&symbols=INR&base=USD';
                $response = Http::get($url)->json();

                if (array_key_exists('success', $response)) {
                    if ($response['success'] == false) {
                        return respondJson($response['error']['message'], $response['error']['message']);
                    }
                }

                return response()->json([
                    'exchangeRate' => array_values($response['rates']),
                ], 200);

            case 'currency_layer':
                $url = 'http://api.currencylayer.com/live?access_key='.$request['key'].'&source=INR&currencies=USD';
                $response = Http::get($url)->json();

                if (array_key_exists('success', $response)) {
                    if ($response['success'] == false) {
                        return respondJson($response['error']['info'], $response['error']['info']);
                    }
                }

                return response()->json([
                    'exchangeRate' => array_values($response['quotes']),
                ], 200);

            case 'open_exchange_rate':
                $url = 'https://openexchangerates.org/api/latest.json?app_id='.$request['key'].'&base=INR&symbols=USD';
                $response = Http::get($url)->json();

                if (array_key_exists('error', $response)) {
                    return respondJson($response['message'], $response['description']);
                }

                return response()->json([
                    'exchangeRate' => array_values($response['rates']),
                ], 200);

            case 'currency_converter':
                $url = $this->getCurrencyConverterUrl($request['driver_config']);
                $url = $url.'/api/v7/convert?apiKey='.$request['key'];

                $query = 'INR_USD';
                $url = $url."&q={$query}".'&compact=y';
                $response = Http::get($url)->json();

                return response()->json([
                    'exchangeRate' => array_values($response[$query]),
                ], 200);
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

    private function getCurrencyConverterUrl($data): string
    {
        return match ($data['type']) {
            'PREMIUM' => 'https://api.currconv.com',
            'PREPAID' => 'https://prepaid.currconv.com',
            'FREE' => 'https://free.currconv.com',
            'DEDICATED' => $data['url'],
        };
    }
}
