<?php

namespace App\Services\ExchangeRate;

use Illuminate\Support\Facades\Http;

class OpenExchangeRateDriver extends ExchangeRateDriver
{
    private string $baseUrl = 'https://openexchangerates.org/api';

    public function getExchangeRate(string $baseCurrency, string $targetCurrency): array
    {
        $url = "{$this->baseUrl}/latest.json?app_id={$this->apiKey}&base={$baseCurrency}&symbols={$targetCurrency}";
        $response = Http::get($url)->json();

        if (array_key_exists('error', $response)) {
            throw new ExchangeRateException($response['description'], $response['message']);
        }

        return array_values($response['rates']);
    }

    public function getSupportedCurrencies(): array
    {
        $url = "{$this->baseUrl}/currencies.json";
        $response = Http::get($url)->json();

        if ($response == null) {
            throw new ExchangeRateException('Server not responding', 'server_error');
        }

        $checkKey = $this->validateConnection();

        return array_keys($response);
    }

    public function validateConnection(): array
    {
        $url = "{$this->baseUrl}/latest.json?app_id={$this->apiKey}&base=INR&symbols=USD";
        $response = Http::get($url)->json();

        if (array_key_exists('error', $response)) {
            if ($response['status'] == 401) {
                throw new ExchangeRateException('Please Enter Valid Provider Key.', 'invalid_key');
            }

            throw new ExchangeRateException($response['description'], $response['message']);
        }

        return array_values($response['rates']);
    }
}
