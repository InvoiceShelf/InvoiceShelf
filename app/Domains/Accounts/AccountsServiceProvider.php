<?php

namespace App\Domains\Accounts;

use App\Adapters\Accounts\EloquentBusinessDefaultsProvisioner;
use App\Adapters\Accounts\EloquentCompanyAddressWriter;
use App\Adapters\Accounts\EloquentCompanyDataPurger;
use App\Adapters\Accounts\EloquentMemberReferencesCleaner;
use App\Adapters\Accounts\LaravelCompanyInvitationSender;
use App\Adapters\Accounts\MediaLibraryCompanyLogoManager;
use App\Adapters\Accounts\MediaLibraryUserAvatarManager;
use App\Adapters\Accounts\ModuleAwareAbilityCatalog;
use App\Domains\Accounts\Application\OAuth\OAuthServer;
use App\Domains\Accounts\Console\GenerateOAuthKeys;
use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Domains\Accounts\Contracts\CompanyAddressWriter;
use App\Domains\Accounts\Contracts\CompanyDataPurger;
use App\Domains\Accounts\Contracts\CompanyDefaultsProvisioner;
use App\Domains\Accounts\Contracts\CompanyInvitationSender;
use App\Domains\Accounts\Contracts\CompanyLogoManager;
use App\Domains\Accounts\Contracts\MemberReferencesCleaner;
use App\Domains\Accounts\Contracts\UserAvatarManager;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Accounts\Policies\CompanyPolicy;
use App\Domains\Accounts\Policies\OwnerPolicy;
use App\Domains\Accounts\Policies\RolePolicy;
use App\Domains\Accounts\Policies\SettingsPolicy;
use App\Domains\Accounts\Policies\UserPolicy;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Silber\Bouncer\Database\Role;

class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AbilityCatalog::class, ModuleAwareAbilityCatalog::class);
        $this->app->bind(CompanyAddressWriter::class, EloquentCompanyAddressWriter::class);
        $this->app->bind(CompanyDataPurger::class, EloquentCompanyDataPurger::class);
        $this->app->bind(CompanyDefaultsProvisioner::class, EloquentBusinessDefaultsProvisioner::class);
        $this->app->bind(CompanyInvitationSender::class, LaravelCompanyInvitationSender::class);
        $this->app->bind(CompanyLogoManager::class, MediaLibraryCompanyLogoManager::class);
        $this->app->bind(MemberReferencesCleaner::class, EloquentMemberReferencesCleaner::class);
        $this->app->bind(UserAvatarManager::class, MediaLibraryUserAvatarManager::class);

        $this->registerOAuthServer();
    }

    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        // Ability names the controllers authorize against, each one routed to
        // the policy method that decides it. Registered in this order.
        $gates = [
            'create company' => [CompanyPolicy::class, 'create'],
            'transfer company ownership' => [CompanyPolicy::class, 'transferOwnership'],
            'delete company' => [CompanyPolicy::class, 'delete'],
            'manage company' => [SettingsPolicy::class, 'manageCompany'],
            'delete multiple users' => [UserPolicy::class, 'deleteMultiple'],
            'owner only' => [OwnerPolicy::class, 'managedByOwner'],
        ];

        foreach ($gates as $ability => $handler) {
            Gate::define($ability, $handler);
        }

        Passport::tokensExpireIn(CarbonInterval::minutes((int) config('passport.access_token_minutes', 60)));
        Passport::refreshTokensExpireIn(CarbonInterval::days((int) config('passport.refresh_token_days', 30)));

        $this->commands([
            GenerateOAuthKeys::class,
        ]);
    }

    /**
     * The OAuth authorization server (Passport) that outside clients get
     * tokens from. Features that issue tokens register as consumers of
     * OAuthServer; the server's routes answer only while one of them is on.
     *
     * Only the authorization code and refresh token grants are in use, so a
     * token is only ever issued after consent in a browser. The device grant
     * is switched off here, before Passport registers its routes; the password
     * and implicit grants are off by default and stay off. Passport always
     * enables client credentials, but no client is ever created with that
     * grant, and the `oauth` guard refuses a token that names no user.
     */
    private function registerOAuthServer(): void
    {
        $this->app->singleton(OAuthServer::class);

        Passport::$deviceCodeGrantEnabled = false;
    }
}
