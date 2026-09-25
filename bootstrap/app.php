<?php

use App\Domains\Accounts\Application\PostLoginRedirect;
use App\Domains\Accounts\Http\Middleware\Authenticate;
use App\Domains\Accounts\Http\Middleware\CompanyMiddleware;
use App\Domains\Accounts\Http\Middleware\EnsureOAuthServerEnabled;
use App\Domains\Accounts\Http\Middleware\RedirectIfAuthenticated;
use App\Domains\Accounts\Http\Middleware\RedirectIfUnauthorized;
use App\Domains\Accounts\Http\Middleware\ScopeBouncer;
use App\Domains\Accounts\Http\Middleware\SuperAdminMiddleware;
use App\Domains\Contacts\Http\Middleware\CustomerGuest;
use App\Domains\Contacts\Http\Middleware\CustomerPortalMiddleware;
use App\Domains\Contacts\Http\Middleware\CustomerRedirectIfAuthenticated;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\RestrictPortalHost;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Platform\Modules\Runtime\ModuleRuntimeAutoloader;
use App\Platform\Operations\Demo\BlockDemoChanges;
use App\Platform\Operations\Http\Middleware\CronJobMiddleware;
use App\Platform\Operations\Http\Middleware\EnsureNotContainerized;
use App\Platform\Operations\Installation\Http\Middleware\EnsureInstalled;
use App\Platform\Operations\Installation\Http\Middleware\RedirectIfInstalled;
use App\Platform\Operations\Installation\Http\Middleware\UseInstallWizardTokenAuth;
use App\Platform\Operations\Managed\EnsureNotManaged;
use App\Platform\Pdf\Http\Middleware\PdfMiddleware;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Lavary\Menu\ServiceProvider;
use League\OAuth2\Server\Exception\OAuthServerException;

// Marketplace modules are installed after Composer's autoload map is built.
// Register their app/ prefixes before package discovery loads enabled module
// service providers during application bootstrap.
ModuleRuntimeAutoloader::registerInstalledModules(dirname(__DIR__).'/Modules');

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        ServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn (Request $request) => PostLoginRedirect::loginUrlFor($request));
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->preventRequestForgery(except: [
            'login',
            'installation/session-login',
        ]);

        $middleware->append([
            CheckForMaintenanceMode::class,
            TrimStrings::class,
            TrustProxies::class,
            UseInstallWizardTokenAuth::class,
            // After TrustProxies, so the host is the one the visitor asked for.
            RestrictPortalHost::class,
        ]);

        $middleware->web([
            EncryptCookies::class,
            PreventRequestForgery::class,
        ]);

        // First in the group, so what the demo refuses is refused before a
        // route binding can answer 404 for a record that is not there.
        $middleware->prependToGroup('api', BlockDemoChanges::class);

        $middleware->statefulApi();
        $middleware->throttleApi('180,1');

        $middleware->replace(Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class);

        $middleware->replaceInGroup('web', Illuminate\Cookie\Middleware\EncryptCookies::class, EncryptCookies::class);

        $middleware->alias([
            'auth' => Authenticate::class,
            'bindings' => SubstituteBindings::class,
            'bouncer' => ScopeBouncer::class,
            'company' => CompanyMiddleware::class,
            'cron-job' => CronJobMiddleware::class,
            'customer' => CustomerRedirectIfAuthenticated::class,
            'customer-guest' => CustomerGuest::class,
            'customer-portal' => CustomerPortalMiddleware::class,
            'guest' => RedirectIfAuthenticated::class,
            'install' => EnsureInstalled::class,
            'not-containerized' => EnsureNotContainerized::class,
            'not-managed' => EnsureNotManaged::class,
            'oauth.enabled' => EnsureOAuthServerEnabled::class,
            'pdf-auth' => PdfMiddleware::class,
            'redirect-if-installed' => RedirectIfInstalled::class,
            'redirect-if-unauthenticated' => RedirectIfUnauthorized::class,
            'super-admin' => SuperAdminMiddleware::class,
        ]);

        $middleware->priority([
            StartSession::class,
            ShareErrorsFromSession::class,
            Authenticate::class,
            AuthenticateSession::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Passport's guard reports every bearer token it cannot verify, which
        // on a public endpoint is routine traffic rather than an error.
        $exceptions->dontReport(OAuthServerException::class);
    })->create();
