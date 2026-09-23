<?php

namespace App\Platform\Mcp;

use App\Domains\Accounts\Application\OAuth\OAuthServer;
use App\Domains\Accounts\Events\CompanyAccessRevoked;
use App\Domains\Accounts\Events\UserAccessRevoked;
use App\Platform\Mcp\Application\ConnectionService;
use App\Platform\Mcp\Application\McpSettings;
use App\Platform\Mcp\Console\DisableMcp;
use App\Platform\Mcp\Console\EnableMcp;
use App\Platform\Mcp\Console\PruneMcp;
use App\Platform\Mcp\Http\Middleware\EnsureMcpEnabled;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\OAuth\ApproveConnectionController;
use App\Platform\Mcp\OAuth\AuthorizeConnectionController;
use App\Platform\Mcp\OAuth\ConsentScreen;
use App\Platform\Mcp\OAuth\RegisterClientController;
use App\Platform\Mcp\Policies\McpConnectionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Passport;

/**
 * The MCP server: an endpoint at /mcp through which AI clients work in one
 * company on a user's behalf, authorized through the accounts domain's OAuth
 * server.
 */
class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(McpSettings::class);

        // Passport's consent and approval endpoints, and laravel/mcp's client
        // registration, with the connection binding added.
        $this->app->bind(AuthorizationController::class, AuthorizeConnectionController::class);
        $this->app->bind(ApproveAuthorizationController::class, ApproveConnectionController::class);
        $this->app->bind(OAuthRegisterController::class, RegisterClientController::class);

        $this->app->when(AuthorizeConnectionController::class)
            ->needs(StatefulGuard::class)
            ->give(fn () => Auth::guard(config('passport.guard')));
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('mcp.enabled', EnsureMcpEnabled::class);

        $this->app->make(OAuthServer::class)->consumer(
            'mcp',
            fn (): bool => $this->app->make(McpSettings::class)->enabled(),
        );

        // A client that asks for no scope gets the MCP one.
        Registrar::ensureMcpScope();
        Passport::setDefaultScope([Registrar::OAUTH_SCOPE]);
        Passport::authorizationView(fn (array $parameters) => $this->app->make(ConsentScreen::class)->render($parameters));

        Gate::policy(McpConnection::class, McpConnectionPolicy::class);

        Event::listen(CompanyAccessRevoked::class, [ConnectionService::class, 'handleCompanyAccessRevoked']);
        Event::listen(UserAccessRevoked::class, [ConnectionService::class, 'handleUserAccessRevoked']);

        $this->registerRateLimiters();

        $this->commands([
            EnableMcp::class,
            DisableMcp::class,
            PruneMcp::class,
        ]);
    }

    /**
     * `mcp-ip` runs before authentication, `mcp` per connection after it.
     * Registration is the one discovery route that writes, so it gets the
     * tightest limit.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('mcp-ip', fn (Request $request) => Limit::perMinute(300)->by('mcp-ip:'.$request->ip()));

        RateLimiter::for('mcp', function (Request $request) {
            $connection = $this->app->bound(McpContext::class) ? $this->app->make(McpContext::class)->connection->id : null;

            return Limit::perMinute(120)->by('mcp:'.($connection ?? $request->ip()));
        });

        RateLimiter::for('mcp-discovery', fn (Request $request) => $request->is('oauth/register')
            ? Limit::perMinute(5)->by('mcp-register:'.$request->ip())
            : Limit::perMinute(30)->by('mcp-discovery:'.$request->ip()));
    }
}
