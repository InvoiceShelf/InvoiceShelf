<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use InvoiceShelf\Modules\Registry;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Models as BouncerModels;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $this->owner = User::where('role', 'super admin')->firstOrFail();
    $this->companyId = $this->owner->companies()->firstOrFail()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->owner, ['*']);

    Registry::flush();
    Registry::registerAbility('catalog-probe', [
        'ability' => 'view-thing',
        'name' => 'View things',
    ]);
});

afterEach(function () {
    Registry::flush();
});

it('appends a registered module ability to the configured catalogue', function () {
    $entries = app(AbilityCatalog::class)->all();
    $probe = collect($entries)->firstWhere('ability', 'catalog-probe:view-thing');

    expect($entries)->toHaveCount(count(config('abilities.abilities')) + 1)
        ->and($probe)->not->toBeNull()
        ->and($probe['name'])->toBe('View things')
        ->and($probe['model'])->toBeNull()
        ->and($probe['depends_on'])->toBe([])
        ->and($probe['owner_only'])->toBeFalse();

    foreach ($entries as $entry) {
        expect($entry)->toHaveKeys(['name', 'ability', 'model', 'depends_on', 'owner_only']);
    }
});

it('drops the module ability again once the registry is emptied', function () {
    Registry::flush();

    expect(collect(app(AbilityCatalog::class)->all())->pluck('ability'))
        ->not->toContain('catalog-probe:view-thing');
});

it('lists the module ability on the abilities endpoint', function () {
    $abilities = getJson('/api/v1/abilities')->assertOk()->json('abilities');

    expect(collect($abilities)->pluck('ability'))->toContain('catalog-probe:view-thing')
        ->and(collect($abilities)->firstWhere('ability', 'catalog-probe:view-thing')['model'])->toBeNull();
});

it('grants and revokes a module ability through the roles endpoint', function () {
    $role = postJson('/api/v1/roles', [
        'name' => 'catalog-probe-role',
        'abilities' => [
            ['ability' => 'dashboard'],
            ['ability' => 'catalog-probe:view-thing'],
        ],
    ])->assertSuccessful()->json('data');

    expect(catalogRoleAbilities($role['id'], $this->companyId))
        ->toContain('catalog-probe:view-thing');

    putJson("/api/v1/roles/{$role['id']}", [
        'name' => 'catalog-probe-role',
        'abilities' => [['ability' => 'dashboard']],
    ])->assertSuccessful();

    expect(catalogRoleAbilities($role['id'], $this->companyId))
        ->not->toContain('catalog-probe:view-thing')
        ->and(catalogRoleAbilities($role['id'], $this->companyId))->toContain('dashboard');
});

it('hands the module ability to a company created while the module is registered', function () {
    $company = Company::factory()->create();

    app(CompanyService::class)->setupDefaults($company);

    expect(catalogOwnerAbilities($company->id))->toContain('catalog-probe:view-thing');
});

/**
 * Every ability name attached to one role, read inside that company's scope.
 *
 * @return list<string>
 */
function catalogRoleAbilities(int $roleId, int $companyId): array
{
    return BouncerFacade::scope()->onceTo($companyId, function () use ($roleId): array {
        $role = BouncerModels::role()->newQuery()->whereKey($roleId)->first();

        return $role === null ? [] : $role->abilities()->pluck('name')->all();
    });
}

/**
 * Every ability name held by a company's `owner` role.
 *
 * @return list<string>
 */
function catalogOwnerAbilities(int $companyId): array
{
    return BouncerFacade::scope()->onceTo($companyId, function () use ($companyId): array {
        $role = BouncerModels::role()->newQuery()
            ->where('name', 'owner')
            ->where('scope', $companyId)
            ->first();

        return $role === null ? [] : $role->abilities()->pluck('name')->all();
    });
}
