<?php

namespace App\Domains\Money\ExchangeRates;

use App\Support\Net\BlockedUrlException;
use App\Support\Net\PrivateNetworkGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CurrencyConverterDriver extends ExchangeRateDriver
{
    public function getExchangeRate(string $baseCurrency, string $targetCurrency): array
    {
        $baseUrl = $this->getBaseUrl();
        $query = "{$baseCurrency}_{$targetCurrency}";
        $url = "{$baseUrl}/api/v7/convert?apiKey={$this->apiKey}&q={$query}&compact=y";
        $response = $this->client()->get($url)->json();

        return array_values($response[$query]);
    }

    public function getSupportedCurrencies(): array
    {
        $baseUrl = $this->getBaseUrl();
        $url = "{$baseUrl}/api/v7/currencies?apiKey={$this->apiKey}";
        $response = $this->client()->get($url)->json();

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
        $response = $this->client()->get($url)->json();

        return array_values($response[$query]);
    }

    /**
     * The URL of a dedicated plan is the user's, and passed the private-network
     * guard only as given: a redirect would take the request, API key and all,
     * wherever the host pointed it, internal addresses included.
     */
    private function client(): PendingRequest
    {
        return Http::withoutRedirecting();
    }

    private function getBaseUrl(): string
    {
        $type = $this->config['type'] ?? 'FREE';

        $url = match ($type) {
            'PREMIUM' => 'https://api.currconv.com',
            'PREPAID' => 'https://prepaid.currconv.com',
            'FREE' => 'https://free.currconv.com',
            'DEDICATED' => $this->config['url'] ?? 'https://free.currconv.com',
        };

        // SSRF guard: the DEDICATED plan lets the user supply this URL.
        try {
            PrivateNetworkGuard::assertAllowed($url);
        } catch (BlockedUrlException $e) {
            throw new ExchangeRateException('Invalid provider URL: '.$e->getMessage(), 'invalid_url');
        }

        return $url;
    }
}
