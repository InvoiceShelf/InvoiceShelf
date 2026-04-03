<?php

namespace App\Services\ExchangeRate;

use Illuminate\Support\Facades\Http;

class CurrencyConverterDriver extends ExchangeRateDriver
{
    public function getExchangeRate(string $baseCurrency, string $targetCurrency): array
    {
        $baseUrl = $this->getBaseUrl();
        $query = "{$baseCurrency}_{$targetCurrency}";
        $url = "{$baseUrl}/api/v7/convert?apiKey={$this->apiKey}&q={$query}&compact=y";
        $response = Http::get($url)->json();

        return array_values($response[$query]);
    }

    public function getSupportedCurrencies(): array
    {
        $baseUrl = $this->getBaseUrl();
        $url = "{$baseUrl}/api/v7/currencies?apiKey={$this->apiKey}";
        $response = Http::get($url)->json();

        if ($response == null) {
            throw new ExchangeRateException('Server not responding', 'server_error');
        }

        if (array_key_exists('results', $response)) {
            return array_keys($response['results']);
        }

        throw new ExchangeRateException('Please Enter Valid Provider Key.', 'invalid_key');
    }

    public function validateConnection(): array
    {
        $baseUrl = $this->getBaseUrl();
        $query = 'INR_USD';
        $url = "{$baseUrl}/api/v7/convert?apiKey={$this->apiKey}&q={$query}&compact=y";
        $response = Http::get($url)->json();

        return array_values($response[$query]);
    }

    private function getBaseUrl(): string
    {
        $type = $this->config['type'] ?? 'FREE';

        return match ($type) {
            'PREMIUM' => 'https://api.currconv.com',
            'PREPAID' => 'https://prepaid.currconv.com',
            'FREE' => 'https://free.currconv.com',
            'DEDICATED' => $this->config['url'] ?? 'https://free.currconv.com',
        };
    }
}
