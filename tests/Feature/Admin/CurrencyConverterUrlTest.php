<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('a dedicated currency converter URL must be a public address', function () {
    Http::fake();

    getJson('/api/v1/supported-currencies?'.http_build_query([
        'driver' => 'currency_converter',
        'key' => 'key',
        'type' => 'DEDICATED',
        'url' => 'http://169.254.169.254',
    ]))->assertStatus(422);

    postJson('/api/v1/exchange-rate-providers', [
        'driver' => 'currency_converter',
        'key' => 'key',
        'currencies' => ['USD'],
        'driver_config' => ['type' => 'DEDICATED', 'url' => 'http://127.0.0.1:8080'],
    ])->assertUnprocessable();

    Http::assertNothingSent();
});

test('a dedicated currency converter URL is not followed through a redirect', function () {
    // A public address passes the check; its reply points at the cloud
    // metadata endpoint, which the check would have refused.
    Http::fake([
        '1.1.1.1/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        '169.254.169.254/*' => Http::response(['results' => ['USD' => []]]),
    ]);

    getJson('/api/v1/supported-currencies?'.http_build_query([
        'driver' => 'currency_converter',
        'key' => 'key',
        'type' => 'DEDICATED',
        'url' => 'http://1.1.1.1',
    ]));

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://1.1.1.1/'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
});
