<?php

use App\Platform\Mcp\Http\Middleware\BindMcpConnection;
use App\Platform\Mcp\Http\Middleware\EnsureMcpEnabled;
use App\Platform\Mcp\McpServiceProvider;
use App\Platform\Mcp\OAuth\ApproveConnectionController;
use App\Platform\Mcp\OAuth\AuthorizeConnectionController;
use App\Platform\Mcp\OAuth\RegisterClientController;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use App\Platform\Mcp\Tools\McpTool;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;

test('the mcp platform capability is registered once and replaces the oauth endpoints it extends', function () {
    expect(app()->getProviders(McpServiceProvider::class))->toHaveCount(1)
        ->and(app('router')->getMiddleware()['mcp.enabled'])->toBe(EnsureMcpEnabled::class)
        ->and(app()->make(AuthorizationController::class))->toBeInstanceOf(AuthorizeConnectionController::class)
        ->and(app()->make(ApproveAuthorizationController::class))->toBeInstanceOf(ApproveConnectionController::class)
        ->and(app()->make(OAuthRegisterController::class))->toBeInstanceOf(RegisterClientController::class);
});

test('the endpoint and discovery routes carry their gates', function () {
    $routes = collect(Route::getRoutes()->getRoutes());

    $mcp = $routes->first(fn ($route) => $route->uri() === 'mcp' && in_array('POST', $route->methods(), true));

    expect($mcp->gatherMiddleware())->toContain('mcp.enabled', 'auth:oauth', BindMcpConnection::class, 'company', 'bouncer', 'throttle:mcp');

    foreach (['.well-known/oauth-protected-resource', '.well-known/oauth-protected-resource/{path}', '.well-known/oauth-authorization-server', '.well-known/oauth-authorization-server/{path}', 'oauth/register'] as $uri) {
        $route = $routes->first(fn ($route) => $route->uri() === $uri);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('mcp.enabled', 'throttle:mcp-discovery');
    }

    expect($routes->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/mcp/'))->every(
        fn ($route) => in_array('auth:sanctum', $route->gatherMiddleware(), true)
    ))->toBeTrue();

    $admin = $routes->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/super-admin/mcp'));

    expect($admin)->toHaveCount(3);

    foreach ($admin as $route) {
        expect($route->getActionName())->toStartWith('App\\Platform\\Mcp\\Http\\Controllers\\Admin\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'super-admin');
    }
});

test('every tool on the server declares its annotations and follows the base class', function () {
    $tools = (fn () => $this->tools)->call(app()->make(InvoiceShelfServer::class, ['transport' => new FakeTransporter]));

    expect($tools)->not->toBeEmpty();

    foreach ($tools as $tool) {
        $reflection = new ReflectionClass($tool);

        expect($reflection->isSubclassOf(McpTool::class))->toBeTrue();

        foreach ([IsReadOnly::class, IsIdempotent::class, IsDestructive::class, IsOpenWorld::class] as $annotation) {
            expect($reflection->getAttributes($annotation))->not->toBeEmpty("{$tool} must declare ".class_basename($annotation));
        }
    }
});
