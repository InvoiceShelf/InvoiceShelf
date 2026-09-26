<?php

use App\Domains\Accounts\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/*
 * A managed install (INVOICESHELF_MANAGED) is run for its owner by a hosting
 * provider, which owns storage, backups, PDF rendering, the server's mail
 * transport and module installation. Those routes refuse; the rest stay.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('the provider-owned settings refuse on a managed install', function (string $method, string $uri) {
    config(['managed.enabled' => true]);

    $this->json($method, $uri)
        ->assertForbidden()
        ->assertJson(['error' => 'managed_mode']);
})->with([
    'file disks' => ['GET', '/api/v1/disks'],
    'backups' => ['GET', '/api/v1/backups'],
    'pdf driver' => ['GET', '/api/v1/pdf/config'],
    'fonts' => ['GET', '/api/v1/fonts/status'],
    'server mail' => ['GET', '/api/v1/mail/config'],
    'marketplace pairing' => ['POST', '/api/v1/modules/pairing/start'],
    'marketplace pairing poll' => ['POST', '/api/v1/modules/pairing/poll'],
]);

test('module installs follow the provider: refused without a writable Modules directory', function () {
    config(['managed.enabled' => true, 'modules.paths.modules' => storage_path('framework/testing/no-such-modules-dir')]);

    $this->postJson('/api/v1/modules/install', ['slug' => 'tasks-projects', 'version' => '1.0.0'])
        ->assertForbidden()->assertJson(['error' => 'managed_mode']);
    $this->postJson('/api/v1/modules/SomeModule/uninstall')->assertForbidden();
    getJson('/api/v1/app/client-manifest')->assertJsonPath('managed.modules_installable', false);
});

test('module installs follow the provider: open with a writable Modules directory', function () {
    $dir = storage_path('framework/testing/modules-mount');
    File::ensureDirectoryExists($dir);
    config(['managed.enabled' => true, 'modules.paths.modules' => $dir]);

    // Past the gate, the request reaches the controller's own validation.
    $this->postJson('/api/v1/modules/install', [])->assertUnprocessable();
    getJson('/api/v1/app/client-manifest')->assertJsonPath('managed.modules_installable', true);
    // Pairing stays with the provider.
    $this->postJson('/api/v1/modules/pairing/start')->assertForbidden();

    File::deleteDirectory($dir);
});

test('everything else stays open on a managed install', function () {
    config(['managed.enabled' => true]);

    getJson('/api/v1/company/mail/company-config')->assertOk();
    getJson('/api/v1/modules')->assertOk();
    getJson('/api/v1/customers')->assertOk();
});

test('an ordinary install is not affected', function () {
    getJson('/api/v1/disks')->assertOk();
    getJson('/api/v1/mail/config')->assertOk();
});

test('the SPA and the client manifest are told', function () {
    config(['managed.enabled' => true, 'managed.support_url' => 'https://help.example.com']);

    getJson('/api/v1/app/client-manifest')
        ->assertOk()
        ->assertJson(['managed_mode' => true, 'managed' => ['support_url' => 'https://help.example.com']]);

    $this->followingRedirects()->get('/login')->assertOk()->assertSee('window.managed_mode = true', false);
});

test('the managed switch never reaches the bootstrap config', function () {
    config(['managed.enabled' => true]);

    expect(config('invoiceshelf'))->not->toHaveKey('managed');
});

test('every provider-owned route carries the managed gate', function () {
    $locked = collect(Router::getRoutes()->getRoutes())->filter(function (Route $route) {
        $uri = $route->uri();

        return Str::is([
            'api/v1/disks*', 'api/v1/disk/*', 'api/v1/backups*', 'api/v1/download-backup',
            'api/v1/pdf/*', 'api/v1/fonts/*', 'api/v1/mail/*', 'api/v1/modules/pairing/*',
        ], $uri) || ($uri === 'api/v1/modules/pairing' && in_array('DELETE', $route->methods(), true));
    });

    expect($locked)->not->toBeEmpty();

    foreach ($locked as $route) {
        expect($route->gatherMiddleware())->toContain('not-managed');
    }

    $installs = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => Str::is(['api/v1/modules/install', 'api/v1/modules/{module}/uninstall'], $route->uri()));
    expect($installs)->toHaveCount(2);
    foreach ($installs as $route) {
        expect($route->gatherMiddleware())->toContain('modules-installable');
    }
});
