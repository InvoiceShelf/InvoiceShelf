<?php

use App\Domains\Accounts\Application\UserCompanyAccessService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Platform\Modules\Infrastructure\BouncerModuleAuthorization;
use Illuminate\Support\Facades\Artisan;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

function hostGrant(User $user, int $companyId, string $ability, ?string $model = null): void
{
    BouncerFacade::scope()->to($companyId);

    $model === null
        ? BouncerFacade::allow($user)->to($ability)
        : BouncerFacade::allow($user)->to($ability, $model);
}

test('module authorization resolves stable resource keys within the supplied company scope', function () {
    $companyA = Company::firstOrFail();
    $companyB = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach([$companyA->id, $companyB->id]);
    $authorization = new BouncerModuleAuthorization(app(UserCompanyAccessService::class));

    hostGrant($user, $companyA->id, 'view-item', Item::class);
    hostGrant($user, $companyA->id, 'dashboard');
    BouncerFacade::scope()->to($companyA->id);

    expect($authorization->allows($user->id, $companyA->id, 'view-item', 'item'))->toBeTrue()
        ->and($authorization->allows($user->id, $companyA->id, 'dashboard'))->toBeTrue()
        ->and($authorization->allows($user->id, $companyB->id, 'view-item', 'item'))->toBeFalse()
        ->and($authorization->allows($user->id, $companyA->id, 'view-item', 'invoice'))->toBeFalse()
        ->and(BouncerFacade::scope()->appendToCacheKey('scope'))->toBe('scope-'.$companyA->id);
});

test('module authorization refuses users outside the company and unknown stable resource keys', function () {
    $company = Company::firstOrFail();
    $outsider = User::factory()->create();
    $authorization = new BouncerModuleAuthorization(app(UserCompanyAccessService::class));

    expect($authorization->allows($outsider->id, $company->id, 'dashboard'))->toBeFalse();

    $member = User::factory()->create();
    $member->companies()->attach($company->id);

    expect(fn () => $authorization->allows($member->id, $company->id, 'view-widget', 'widget'))
        ->toThrow(LogicException::class);
});

test('module authorization accepts global roles in allowed companies but not restricted ones', function () {
    $company = Company::firstOrFail();
    $restricted = Company::factory()->create();
    $user = User::factory()->create(['role' => 'user']);

    app(UserCompanyAccessService::class)->syncUserAccess($user, ['manager'], [$restricted->id]);

    $authorization = new BouncerModuleAuthorization(app(UserCompanyAccessService::class));

    expect($authorization->allows($user->id, $company->id, 'dashboard'))->toBeTrue()
        ->and($authorization->allows($user->id, $company->id, 'view-invoice', 'invoice'))->toBeTrue()
        ->and($authorization->allows($user->id, $restricted->id, 'dashboard'))->toBeFalse();
});
