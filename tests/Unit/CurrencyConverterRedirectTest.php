<?php

use App\Domains\Money\ExchangeRates\CurrencyConverterDriver;
use Illuminate\Support\Facades\Http;

test('a dedicated currency converter URL is not followed through a redirect', function () {
    // A public address passes the private-network guard; its reply points at
    // the cloud metadata endpoint, which the guard would have refused.
    Http::fake([
        '1.1.1.1/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        '169.254.169.254/*' => Http::response(['INR_USD' => ['val' => 1]]),
    ]);

    $driver = new CurrencyConverterDriver('key', ['type' => 'DEDICATED', 'url' => 'http://1.1.1.1']);

    try {
        $driver->validateConnection();
    } catch (Throwable) {
        // A refused redirect leaves nothing to parse; the point is where the request went.
    }

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://1.1.1.1/'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
});
