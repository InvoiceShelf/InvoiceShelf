<?php

namespace App\Services\ExchangeRate;

use Illuminate\Support\Facades\Http;

class CurrencyFreakDriver extends ExchangeRateDriver
{
    private string $baseUrl = 'https://api.currencyfreaks.com';

    public function getExchangeRate(string $baseCurrency, string $targetCurrency): array
    {
        $url = "{$this->baseUrl}/latest?apikey={$this->apiKey}&symbols={$targetCurrency}&base={$baseCurrency}";
        $response = Http::get($url)->json();

        if (array_key_exists('success', $response) && $response['success'] == false) {
            throw new ExchangeRateException($response['error']['message'], 'provider_error');
        }

        return array_values($response['rates']);
    }

    public function getSupportedCurrencies(): array
    {
        $url = "{$this->baseUrl}/currency-symbols";
        $response = Http::get($url)->json();

        if ($response == null) {
            throw new ExchangeRateException('Server not responding', 'server_error');
        }

        $checkKey = $this->validateConnection();

        return array_keys($response);
    }

    public function validateConnection(): array
    {
        $url = "{$this->baseUrl}/latest?apikey={$this->apiKey}&symbols=INR&base=USD";
        $response = Http::get($url)->json();

        if ($response == null) {
            throw new ExchangeRateException('Server not responding', 'server_error');
        }

        if (array_key_exists('success', $response) && array_key_exists('error', $response)) {
            if ($response['error']['status'] == 404) {
                throw new ExchangeRateException('Please Enter Valid Provider Key.', 'invalid_key');
            }
        }

        return array_values($response['rates']);
    }
}
