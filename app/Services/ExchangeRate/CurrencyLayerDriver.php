<?php

namespace App\Services\ExchangeRate;

use Illuminate\Support\Facades\Http;

class CurrencyLayerDriver extends ExchangeRateDriver
{
    private string $baseUrl = 'http://api.currencylayer.com';

    public function getExchangeRate(string $baseCurrency, string $targetCurrency): array
    {
        $url = "{$this->baseUrl}/live?access_key={$this->apiKey}&source={$baseCurrency}&currencies={$targetCurrency}";
        $response = Http::get($url)->json();

        if (array_key_exists('success', $response) && $response['success'] == false) {
            throw new ExchangeRateException($response['error']['info'], 'provider_error');
        }

        return array_values($response['quotes']);
    }

    public function getSupportedCurrencies(): array
    {
        $url = "{$this->baseUrl}/list?access_key={$this->apiKey}";
        $response = Http::get($url)->json();

        if ($response == null) {
            throw new ExchangeRateException('Server not responding', 'server_error');
        }

        if (array_key_exists('currencies', $response)) {
            return array_keys($response['currencies']);
        }

        throw new ExchangeRateException('Please Enter Valid Provider Key.', 'invalid_key');
    }

    public function validateConnection(): array
    {
        $url = "{$this->baseUrl}/live?access_key={$this->apiKey}&source=INR&currencies=USD";
        $response = Http::get($url)->json();

        if (array_key_exists('success', $response) && $response['success'] == false) {
            throw new ExchangeRateException($response['error']['info'], 'provider_error');
        }

        return array_values($response['quotes']);
    }
}
