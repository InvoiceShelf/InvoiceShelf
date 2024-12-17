<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \Lavary\Menu\ServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->validateCsrfTokens(except: [
            'login',
        ]);

        $middleware->append([
            \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
            \App\Http\Middleware\TrimStrings::class,
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\ConfigMiddleware::class,
        ]);

        $middleware->web([
            \App\Http\Middleware\EncryptCookies::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $middleware->statefulApi();
        $middleware->throttleApi('180,1');

        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\TrustProxies::class);

        $middleware->replaceInGroup('web', \Illuminate\Cookie\Middleware\EncryptCookies::class, \App\Http\Middleware\EncryptCookies::class);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'bouncer' => \App\Http\Middleware\ScopeBouncer::class,
            'company' => \App\Http\Middleware\CompanyMiddleware::class,
            'cron-job' => \App\Http\Middleware\CronJobMiddleware::class,
            'customer' => \App\Http\Middleware\CustomerRedirectIfAuthenticated::class,
            'customer-guest' => \App\Http\Middleware\CustomerGuest::class,
            'customer-portal' => \App\Http\Middleware\CustomerPortalMiddleware::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'install' => \App\Http\Middleware\InstallationMiddleware::class,
            'pdf-auth' => \App\Http\Middleware\PdfMiddleware::class,
            'redirect-if-installed' => \App\Http\Middleware\RedirectIfInstalled::class,
            'redirect-if-unauthenticated' => \App\Http\Middleware\RedirectIfUnauthorized::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\Authenticate::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
