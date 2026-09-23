<?php

use App\Adapters\Accounts\EloquentBusinessDefaultsProvisioner;
use App\Adapters\Accounts\EloquentCompanyAddressWriter;
use App\Adapters\Accounts\EloquentCompanyDataPurger;
use App\Adapters\Accounts\EloquentMemberReferencesCleaner;
use App\Adapters\Accounts\LaravelCompanyInvitationSender;
use App\Adapters\Accounts\MediaLibraryCompanyLogoManager;
use App\Adapters\Accounts\MediaLibraryUserAvatarManager;
use App\Domains\Accounts\AccountsServiceProvider;
use App\Domains\Accounts\Contracts\CompanyAddressWriter;
use App\Domains\Accounts\Contracts\CompanyDataPurger;
use App\Domains\Accounts\Contracts\CompanyDefaultsProvisioner;
use App\Domains\Accounts\Contracts\CompanyInvitationSender;
use App\Domains\Accounts\Contracts\CompanyLogoManager;
use App\Domains\Accounts\Contracts\MemberReferencesCleaner;
use App\Domains\Accounts\Contracts\UserAvatarManager;
use App\Domains\Accounts\Http\Middleware\Authenticate;
use App\Domains\Accounts\Http\Middleware\CompanyMiddleware;
use App\Domains\Accounts\Http\Middleware\EnsureOAuthServerEnabled;
use App\Domains\Accounts\Http\Middleware\RedirectIfAuthenticated;
use App\Domains\Accounts\Http\Middleware\RedirectIfUnauthorized;
use App\Domains\Accounts\Http\Middleware\ScopeBouncer;
use App\Domains\Accounts\Http\Middleware\SuperAdminMiddleware;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Accounts\Policies\CompanyPolicy;
use App\Domains\Accounts\Policies\RolePolicy;
use App\Domains\Accounts\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Silber\Bouncer\Database\Role;

test('the accounts domain owns account behavior and authorization', function () {
    expect(app()->getProviders(AccountsServiceProvider::class))->toHaveCount(1)
        ->and(app(CompanyAddressWriter::class))->toBeInstanceOf(EloquentCompanyAddressWriter::class)
        ->and(app(CompanyDataPurger::class))->toBeInstanceOf(EloquentCompanyDataPurger::class)
        ->and(app(CompanyDefaultsProvisioner::class))->toBeInstanceOf(EloquentBusinessDefaultsProvisioner::class)
        ->and(app(CompanyInvitationSender::class))->toBeInstanceOf(LaravelCompanyInvitationSender::class)
        ->and(app(CompanyLogoManager::class))->toBeInstanceOf(MediaLibraryCompanyLogoManager::class)
        ->and(app(MemberReferencesCleaner::class))->toBeInstanceOf(EloquentMemberReferencesCleaner::class)
        ->and(app(UserAvatarManager::class))->toBeInstanceOf(MediaLibraryUserAvatarManager::class)
        ->and(Gate::getPolicyFor(Company::class))->toBeInstanceOf(CompanyPolicy::class)
        ->and(Gate::getPolicyFor(User::class))->toBeInstanceOf(UserPolicy::class)
        ->and(Gate::getPolicyFor(Role::class))->toBeInstanceOf(RolePolicy::class)
        ->and(Gate::has('create company'))->toBeTrue()
        ->and(Gate::has('transfer company ownership'))->toBeTrue()
        ->and(Gate::has('delete company'))->toBeTrue()
        ->and(Gate::has('manage company'))->toBeTrue()
        ->and(Gate::has('delete multiple users'))->toBeTrue()
        ->and(Gate::has('owner only'))->toBeTrue();

    expect(class_exists('App\\Services\\Company\\CompanyService'))->toBeFalse()
        ->and(class_exists('App\\Services\\Company\\InvitationService'))->toBeFalse()
        ->and(class_exists('App\\Services\\Company\\MemberService'))->toBeFalse()
        ->and(class_exists('App\\Policies\\CompanyPolicy'))->toBeFalse()
        ->and(class_exists('App\\Policies\\UserPolicy'))->toBeFalse()
        ->and(class_exists('App\\Policies\\RolePolicy'))->toBeFalse()
        ->and(class_exists('App\\Policies\\SettingsPolicy'))->toBeFalse()
        ->and(class_exists('App\\Policies\\OwnerPolicy'))->toBeFalse()
        ->and(class_exists('App\\Mail\\CompanyInvitationMail'))->toBeFalse()
        ->and(class_exists('App\\Notifications\\MailResetPasswordNotification'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\CompaniesController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\UsersController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Members\\MembersController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Role\\RolesController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Settings\\CompanyController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\CompanyCollection'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\CompanyResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\UserResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\RoleCollection'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\UserCollection'))->toBeFalse();
});

test('account middleware aliases resolve to the accounts domain', function () {
    $middleware = app('router')->getMiddleware();

    expect($middleware['auth'])->toBe(Authenticate::class)
        ->and($middleware['company'])->toBe(CompanyMiddleware::class)
        ->and($middleware['guest'])->toBe(RedirectIfAuthenticated::class)
        ->and($middleware['oauth.enabled'])->toBe(EnsureOAuthServerEnabled::class)
        ->and($middleware['redirect-if-unauthenticated'])->toBe(RedirectIfUnauthorized::class)
        ->and($middleware['bouncer'])->toBe(ScopeBouncer::class)
        ->and($middleware['super-admin'])->toBe(SuperAdminMiddleware::class);
});

test('the accounts domain preserves public and super-admin routes', function () {
    $routes = collect(Route::getRoutes()->getRoutes());
    $publicUris = [
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/password/email',
        'api/v1/auth/register-with-invitation',
        'api/v1/auth/reset/password',
        'api/v1/invitations/{token}/details',
        'login',
        'auth/logout',
        'invitations/{token}/decline',
    ];
    $publicRoutes = $routes
        ->filter(fn ($route): bool => in_array($route->uri(), $publicUris, true))
        ->filter(fn ($route): bool => str_starts_with($route->getActionName(), 'App\\Domains\\Accounts\\Http\\Controllers\\'));

    expect($publicRoutes)->toHaveCount(count($publicUris));

    foreach ($publicRoutes as $route) {
        expect($route->getActionName())->toStartWith('App\\Domains\\Accounts\\Http\\Controllers\\');
    }

    // Routes other areas own under the same prefix. Listed rather than
    // filtered by namespace, so a new one has to be added here on purpose.
    $notOurs = [
        'api/v1/super-admin/dashboard',
        'api/v1/super-admin/currencies',
        'api/v1/super-admin/currencies/refresh',
        'api/v1/super-admin/mcp',
        'api/v1/super-admin/mcp/keys',
    ];

    $adminRoutes = $routes
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/super-admin/'))
        ->reject(fn ($route): bool => in_array($route->uri(), $notOurs, true));

    expect($adminRoutes)->toHaveCount(8);

    foreach ($adminRoutes as $route) {
        expect($route->getActionName())->toStartWith('App\\Domains\\Accounts\\Http\\Controllers\\Admin\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum');

        if ($route->uri() !== 'api/v1/super-admin/stop-impersonating') {
            expect($route->gatherMiddleware())->toContain('super-admin');
        }
    }
});

test('the accounts domain preserves company account routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->getActionName(), 'App\\Domains\\Accounts\\Http\\Controllers\\'))
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->reject(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/auth/'))
        ->reject(fn ($route): bool => $route->uri() === 'api/v1/invitations/{token}/details')
        ->reject(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/super-admin/'));

    expect($routes)->toHaveCount(32);

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('auth:sanctum', 'company');

        if (! preg_match('#^api/v1/(?:companies|members|transfer/)#', $route->uri())) {
            expect($route->gatherMiddleware())->toContain('bouncer');
        }
    }
});

test('the accounts domain owns the oauth server and gates every passport route', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'passport.'));

    expect($routes->map->getName()->sort()->values()->all())->toBe([
        'passport.authorizations.approve',
        'passport.authorizations.authorize',
        'passport.authorizations.deny',
        'passport.token',
        'passport.token.refresh',
    ]);

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('oauth.enabled');
    }

    expect(config('auth.guards.oauth.driver'))->toBe('passport')
        ->and(config('passport.guard'))->toBe('web');
});
