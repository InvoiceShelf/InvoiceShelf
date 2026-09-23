<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use InvoiceShelf\Modules\Registry;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * The cross-origin contract a thin client depends on.
 *
 * Two independent things have to hold. The client's origin must be allowed by
 * config/cors.php on every path it touches, and it must NOT look like this
 * app's own frontend to Sanctum, or session and CSRF handling would be put in
 * front of bearer requests that carry neither.
 */
const CLIENT_ORIGIN = 'https://app.invoiceshelf.internal';

const CLIENT_CAPACITOR_ORIGIN = 'capacitor://app.invoiceshelf.internal';

/**
 * Re-evaluate config/cors.php with the given environment in place.
 *
 * The file reads env() at load time, which is the only way to exercise how it
 * turns CORS_ALLOWED_ORIGINS and INVOICESHELF_CLIENT_HOSTNAME into an
 * allow-list. A null value means "not set at all".
 *
 * @param  array<string, string|null>  $environment
 * @return array<string, mixed>
 */
function corsConfigWithEnvironment(array $environment): array
{
    $restore = [];

    foreach ($environment as $key => $value) {
        $current = getenv($key);
        $restore[$key] = $current === false ? null : $current;

        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);

        if ($value !== null) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }

    try {
        return require base_path('config/cors.php');
    } finally {
        foreach ($restore as $key => $value) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            if ($value !== null) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
    }
}

beforeEach(function () {
    Registry::flush();

    // Pinned so a developer's own CORS_ALLOWED_ORIGINS cannot decide these
    // outcomes. What the file makes of that variable is asserted separately.
    config(['cors.allowed_origins' => [CLIENT_CAPACITOR_ORIGIN, CLIENT_ORIGIN]]);

    $this->assetDirectory = storage_path('app/thin-client-cors-test');
    File::ensureDirectoryExists($this->assetDirectory);
});

afterEach(function () {
    Registry::flush();
    File::deleteDirectory($this->assetDirectory);
});

test('a preflight from the client origin is answered with the client origin', function () {
    $response = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
        'HTTP_ORIGIN' => CLIENT_CAPACITOR_ORIGIN,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe(CLIENT_CAPACITOR_ORIGIN)
        ->and(strtolower((string) $response->headers->get('Access-Control-Allow-Headers')))->toContain('company')
        ->and($response->headers->get('Access-Control-Max-Age'))->toBe('7200')
        // Clients carry a bearer token, never the session cookie; allowing
        // credentials would buy nothing and pin every response to one origin.
        ->and($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse();
});

test('a preflight from any other origin is not allowed', function () {
    $response = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
        'HTTP_ORIGIN' => 'https://not-the-client.example.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});

test('module assets and PDF routes are reachable from the client origin', function () {
    $path = $this->assetDirectory.'/cors-probe.js';
    File::put($path, 'export const probe = true;');
    Registry::registerScript('cors-probe', $path);

    $script = $this->get('/modules/scripts/cors-probe', ['Origin' => CLIENT_ORIGIN]);

    // The gate on a PDF route redirects an anonymous caller, but the allow
    // header is added regardless: what is under test is that the path is
    // covered at all, not what the route answers.
    $pdf = $this->get('/invoices/pdf/no-such-document', ['Origin' => CLIENT_ORIGIN]);

    expect($script->headers->get('Access-Control-Allow-Origin'))->toBe(CLIENT_ORIGIN)
        ->and($pdf->headers->get('Access-Control-Allow-Origin'))->toBe(CLIENT_ORIGIN);
});

test('the client origin is not treated as this app own frontend', function () {
    $fromClient = Request::create('/api/v1/auth/login', 'POST');
    $fromClient->headers->set('Origin', CLIENT_ORIGIN);

    // The trap the client hostname exists to avoid: Sanctum's default stateful
    // list contains localhost, which is Capacitor's own default on Android.
    $fromLocalhost = Request::create('/api/v1/auth/login', 'POST');
    $fromLocalhost->headers->set('Origin', 'https://localhost');

    expect(EnsureFrontendRequestsAreStateful::fromFrontend($fromClient))->toBeFalse()
        ->and(EnsureFrontendRequestsAreStateful::fromFrontend($fromLocalhost))->toBeTrue();
});

test('a bearer sign-in from the client origin succeeds and starts no session', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    $response = $this->withHeaders(['Origin' => CLIENT_ORIGIN])
        ->postJson('/api/v1/auth/login', [
            'username' => 'demo@invoiceshelf.com',
            'password' => 'demo',
            'device_name' => 'Pixel 9',
        ]);

    $response->assertOk()->assertJson(['type' => 'Bearer'])->assertJsonStructure(['token']);

    expect(collect($response->headers->getCookies())->map(fn ($cookie) => $cookie->getName()))
        ->not->toContain(config('session.cookie'));
});

test('the allowed origins are built from the client hostname unless the env names them', function () {
    $default = corsConfigWithEnvironment([
        'INVOICESHELF_CLIENT_HOSTNAME' => null,
        'CORS_ALLOWED_ORIGINS' => null,
    ]);

    $renamed = corsConfigWithEnvironment([
        'INVOICESHELF_CLIENT_HOSTNAME' => 'phone.example.test',
        'CORS_ALLOWED_ORIGINS' => null,
    ]);

    $listed = corsConfigWithEnvironment([
        'INVOICESHELF_CLIENT_HOSTNAME' => null,
        'CORS_ALLOWED_ORIGINS' => ' http://127.0.0.1:4173 , ,https://app.example.test ',
    ]);

    expect($default['allowed_origins'])->toBe([CLIENT_CAPACITOR_ORIGIN, CLIENT_ORIGIN])
        ->and($renamed['allowed_origins'])->toBe(['capacitor://phone.example.test', 'https://phone.example.test'])
        ->and($listed['allowed_origins'])->toBe(['http://127.0.0.1:4173', 'https://app.example.test'])
        ->and($default['paths'])->toContain('api/*', 'modules/scripts/*', 'modules/styles/*', 'reports/*', 'invoices/pdf/*', 'estimates/pdf/*', 'payments/pdf/*')
        ->and($default['supports_credentials'])->toBeFalse();
});
