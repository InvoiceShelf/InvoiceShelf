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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
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
    }
}
