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
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
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
            \InvoiceShelf\Http\Middleware\TrimStrings::class,
            \InvoiceShelf\Http\Middleware\TrustProxies::class,
            \InvoiceShelf\Http\Middleware\ConfigMiddleware::class,
        ]);

        $middleware->web([
            \InvoiceShelf\Http\Middleware\EncryptCookies::class,
            \InvoiceShelf\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $middleware->statefulApi();
        $middleware->throttleApi('180,1');

        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\TrustProxies::class);

        $middleware->replaceInGroup('web', \Illuminate\Cookie\Middleware\EncryptCookies::class, \App\Http\Middleware\EncryptCookies::class);

        $middleware->alias([
            'auth' => \InvoiceShelf\Http\Middleware\Authenticate::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'bouncer' => \InvoiceShelf\Http\Middleware\ScopeBouncer::class,
            'company' => \InvoiceShelf\Http\Middleware\CompanyMiddleware::class,
            'cron-job' => \InvoiceShelf\Http\Middleware\CronJobMiddleware::class,
            'customer' => \InvoiceShelf\Http\Middleware\CustomerRedirectIfAuthenticated::class,
            'customer-guest' => \InvoiceShelf\Http\Middleware\CustomerGuest::class,
            'customer-portal' => \InvoiceShelf\Http\Middleware\CustomerPortalMiddleware::class,
            'guest' => \InvoiceShelf\Http\Middleware\RedirectIfAuthenticated::class,
            'install' => \InvoiceShelf\Http\Middleware\InstallationMiddleware::class,
            'pdf-auth' => \InvoiceShelf\Http\Middleware\PdfMiddleware::class,
            'redirect-if-installed' => \InvoiceShelf\Http\Middleware\RedirectIfInstalled::class,
            'redirect-if-unauthenticated' => \InvoiceShelf\Http\Middleware\RedirectIfUnauthorized::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \InvoiceShelf\Http\Middleware\Authenticate::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
