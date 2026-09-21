<?php

use App\Providers\AppServiceProvider;

/**
 * Re-run the provider's scheme decision against a given configuration.
 *
 * The provider settles this at boot, long before a test can speak, so the
 * method is invoked again over the configuration under test, the same way
 * BootstrapMenuTest re-runs addMenus().
 */
function urlSchemeWith(mixed $forceHttps, string $appUrl): string
{
    config(['app.force_https' => $forceHttps, 'app.url' => $appUrl]);

    app()->getProvider(AppServiceProvider::class)->bootHttps();

    return parse_url(url('/admin/dashboard'), PHP_URL_SCHEME);
}

test('an https APP_URL is enough to generate https URLs', function () {
    expect(urlSchemeWith(null, 'https://invoice.example.com'))->toBe('https');
});

test('a plain http APP_URL leaves the request scheme alone', function () {
    expect(urlSchemeWith(null, 'http://invoice.example.com'))->toBe('http');
});

test('FORCE_HTTPS forces https even when APP_URL says otherwise', function (mixed $enabled) {
    expect(urlSchemeWith($enabled, 'http://invoice.example.com'))->toBe('https');
})->with([true, 'true', '1', 'on']);

test('FORCE_HTTPS false wins over an https APP_URL', function (mixed $disabled) {
    expect(urlSchemeWith($disabled, 'https://invoice.example.com'))->toBe('http');
})->with([false, 'false', '0']);

test('an empty FORCE_HTTPS counts as unset, not as a refusal', function (mixed $blank) {
    expect(urlSchemeWith($blank, 'https://invoice.example.com'))->toBe('https');
})->with([null, '', '   ']);
