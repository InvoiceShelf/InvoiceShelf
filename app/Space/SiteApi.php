<?php

namespace App\Space;

use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Implementation taken from Akaunting - https://github.com/akaunting/akaunting
trait SiteApi
{
    protected static function getRemote($url, $data = [], $token = null)
    {
        $client = new Client(['verify' => false, 'base_uri' => config('invoiceshelf.base_url').'/']);

        $headers['headers'] = [
            'Accept' => 'application/json',
            'Referer' => url('/'),
            'Authorization' => "Bearer {$token}",
            'invoiceshelf' => Setting::getSetting('version'),
        ];

        $data['http_errors'] = false;

        $data = array_merge($data, $headers);

        try {
            $result = $client->get($url, $data);
        } catch (RequestException $e) {
            $result = $e;
        }

        return $result;
    }
}
