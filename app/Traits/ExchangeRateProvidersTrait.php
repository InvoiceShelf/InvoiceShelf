<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;

trait ExchangeRateProvidersTrait
{
    public function getExchangeRate($filter, $baseCurrencyCode, $currencyCode, $date = null)
    {
        switch ($filter['driver']) {
            case 'currency_freak':
                if ($date) {
                    $url = 'https://api.currencyfreaks.com/v2.0/rates/historical?apikey='.$filter['key']."&date={$date}&symbols={$currencyCode}&base={$baseCurrencyCode}";
                } else {
                    $url = 'https://api.currencyfreaks.com/latest?apikey='.$filter['key']."&symbols={$currencyCode}&base={$baseCurrencyCode}";
                }

                $httpResponse = Http::get($url);

                if ($httpResponse->failed()) {
                    return respondJson('http_error', 'Failed to connect to exchange rate provider');
                }

                $response = $httpResponse->json();

                if (!is_array($response)) {
                    return respondJson('invalid_response', 'Invalid response from exchange rate provider');
                }

                if (array_key_exists('success', $response)) {
                    if ($response['success'] == false) {
                        return respondJson($response['error']['message'], $response['error']['message']);
                    }
                }

                return response()->json([
                    'exchangeRate' => array_values($response['rates']),
                ], 200);

                break;

            case 'currency_layer':
                if ($date) {
                    $url = 'http://api.currencylayer.com/historical?access_key='.$filter['key']."&date={$date}&source={$baseCurrencyCode}&currencies={$currencyCode}";
                } else {
                    $url = 'http://api.currencylayer.com/live?access_key='.$filter['key']."&source={$baseCurrencyCode}&currencies={$currencyCode}";
                }

                $httpResponse = Http::get($url);

                if ($httpResponse->failed()) {
                    return respondJson('http_error', 'Failed to connect to exchange rate provider');
                }

                $response = $httpResponse->json();

                if (!is_array($response)) {
                    return respondJson('invalid_response', 'Invalid response from exchange rate provider');
                }

                if (array_key_exists('success', $response)) {
                    if ($response['success'] == false) {
                        return respondJson($response['error']['info'], $response['error']['info']);
                    }
                }

                return response()->json([
                    'exchangeRate' => array_values($response['quotes']),
                ], 200);

                break;

            case 'open_exchange_rate':
                if ($date) {
                    $url = "https://openexchangerates.org/api/historical/{$date}.json?app_id=".$filter['key']."&base={$baseCurrencyCode}&symbols={$currencyCode}";
                } else {
                    $url = 'https://openexchangerates.org/api/latest.json?app_id='.$filter['key']."&base={$baseCurrencyCode}&symbols={$currencyCode}";
                }

                $httpResponse = Http::get($url);

                if ($httpResponse->failed()) {
                    return respondJson('http_error', 'Failed to connect to exchange rate provider');
                }

                $response = $httpResponse->json();

                if (!is_array($response)) {
                    return respondJson('invalid_response', 'Invalid response from exchange rate provider');
                }

                if (array_key_exists('error', $response)) {
                    return respondJson($response['message'], $response['description']);
                }

                return response()->json([
                    'exchangeRate' => array_values($response['rates']),
                ], 200);

                break;

            case 'currency_converter':
                $url = $this->getCurrencyConverterUrl($filter['driver_config']);
                $url = $url.'/api/v7/convert?apiKey='.$filter['key'];

                $query = "{$baseCurrencyCode}_{$currencyCode}";
                $url = $url."&q={$query}".'&compact=y';

                if ($date) {
                    $url .= "&date={$date}";
                }

                $httpResponse = Http::get($url);

                if ($httpResponse->failed()) {
                    return respondJson('http_error', 'Failed to connect to exchange rate provider');
                }

                $response = $httpResponse->json();

                if (!is_array($response)) {
                    return respondJson('invalid_response', 'Invalid response from exchange rate provider');
                }

                if ($date && isset($response[$query][$date])) {
                    return response()->json([
                        'exchangeRate' => array_values([$response[$query][$date]]),
                    ], 200);
                }

                return response()->json([
                    'exchangeRate' => array_values($response[$query]),
                ], 200);

                break;

            case 'frankfurter':
                if ($date) {
                    $url = "https://api.frankfurter.dev/v1/{$date}?from={$baseCurrencyCode}&to={$currencyCode}";
                } else {
                    $url = "https://api.frankfurter.dev/v1/latest?from={$baseCurrencyCode}&to={$currencyCode}";
                }

                $httpResponse = Http::get($url);

                if ($httpResponse->failed()) {
                    return respondJson('http_error', 'Failed to connect to exchange rate provider');
                }

                $response = $httpResponse->json();

                if (!is_array($response)) {
                    return respondJson('invalid_response', 'Invalid response from exchange rate provider');
                }

                if (array_key_exists('message', $response) && $response['message'] === 'not found') {
                    return respondJson('Error', 'Service unavailable');
                }

                if (!isset($response['rates']) || !is_array($response['rates'])) {
                    return respondJson('invalid_response', 'Invalid exchange rate data received');
                }

                return response()->json([
                    'exchangeRate' => array_values($response['rates']),
                ], 200);

                break;
        }
    }

    public function getCurrencyConverterUrl($data)
    {
        switch ($data['type']) {
            case 'PREMIUM':
                return 'https://api.currconv.com';

                break;

            case 'PREPAID':
                return 'https://prepaid.currconv.com';

                break;

            case 'FREE':
                return 'https://free.currconv.com';

                break;

            case 'DEDICATED':
                return $data['url'];

                break;
        }
    }

    public function getSupportedCurrencies($request)
    {
        $message = 'Please Enter Valid Provider Key.';
        $error = 'invalid_key';

        $server_message = 'Server not responding';
        $error_message = 'server_error';

        switch ($request->driver) {
            case 'currency_freak':
                $url = 'https://api.currencyfreaks.com/currency-symbols';
                $response = Http::get($url)->json();
                $checkKey = $this->getUrl($request);

                if ($response == null || $checkKey == null) {
                    return respondJson($error_message, $server_message);
                }

                if (array_key_exists('success', $checkKey) && array_key_exists('error', $checkKey)) {
                    if ($checkKey['error']['status'] == 404) {
                        return respondJson($error, $message);
                    }
                }

                return response()->json(['supportedCurrencies' => array_keys($response)]);

                break;

            case 'currency_layer':
                $url = 'http://api.currencylayer.com/list?access_key='.$request->key;
                $response = Http::get($url)->json();

                if ($response == null) {
                    return respondJson($error_message, $server_message);
                }

                if (array_key_exists('currencies', $response)) {
                    return response()->json(['supportedCurrencies' => array_keys($response['currencies'])]);
                }

                return respondJson($error, $message);

                break;

            case 'open_exchange_rate':
                $url = 'https://openexchangerates.org/api/currencies.json';
                $response = Http::get($url)->json();
                $checkKey = $this->getUrl($request);

                if ($response == null || $checkKey == null) {
                    return respondJson($error_message, $server_message);
                }

                if (array_key_exists('error', $checkKey)) {
                    if ($checkKey['status'] == 401) {
                        return respondJson($error, $message);
                    }
                }

                return response()->json(['supportedCurrencies' => array_keys($response)]);

                break;

            case 'currency_converter':
                $response = $this->getUrl($request);

                if ($response == null) {
                    return respondJson($error_message, $server_message);
                }

                if (array_key_exists('results', $response)) {
                    return response()->json(['supportedCurrencies' => array_keys($response['results'])]);
                }

                return respondJson($error, $message);

                break;

            case 'frankfurter':
                $url = 'https://api.frankfurter.dev/v1/currencies';
                $httpResponse = Http::get($url);

                if ($httpResponse->failed() || !is_array($httpResponse->json())) {
                    return respondJson($error_message, $server_message);
                }

                $response = $httpResponse->json();

                return response()->json(['supportedCurrencies' => array_keys($response)]);

                break;
        }
    }

    public function getUrl($request)
    {
        switch ($request->driver) {
            case 'currency_freak':
                $url = 'https://api.currencyfreaks.com/latest?apikey='.$request->key.'&symbols=INR&base=USD';

                return Http::get($url)->json();

                break;

            case 'currency_layer':
                $url = 'http://api.currencylayer.com/live?access_key='.$request->key.'&source=INR&currencies=USD';

                return Http::get($url)->json();

                break;

            case 'open_exchange_rate':
                $url = 'https://openexchangerates.org/api/latest.json?app_id='.$request->key.'&base=INR&symbols=USD';

                return Http::get($url)->json();

                break;

            case 'currency_converter':
                $url = $this->getCurrencyConverterUrl($request).'/api/v7/currencies?apiKey='.$request->key;

                return Http::get($url)->json();

                break;

            case 'frankfurter':
                $url = 'https://api.frankfurter.dev/v1/latest?from=INR&to=USD';

                try {
                    $httpResponse = Http::get($url);
                    if ($httpResponse->failed()) {
                        return null;
                    }
                    return $httpResponse->json();
                } catch (\Exception $e) {
                    return null;
                }

                break;
        }
    }
}
