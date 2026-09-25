<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Application\RoleGrantWriter;
use App\Domains\Accounts\Application\RolePresetService;
use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\RolePreset;
use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Modules\Application\ModuleAbilitySync;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvoiceShelf\Modules\Registry;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * Role presets: roles the super administrator defines once, which every
 * company holds as a copy it can assign but not change.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->admin = User::query()->where('role', 'super admin')->firstOrFail();
    $this->company = $this->admin->companies()->firstOrFail();
});

/**
 * A company's copy of a preset, read past the Bouncer scope.
 */
function presetCopy(Company $company, string $roleName): ?Role
{
    return Role::query()->withoutGlobalScopes()->where('name', $roleName)->where('scope', $company->id)->first();
}

/**
 * The ability names a role holds, read in its own company.
 *
 * @return list<string>
 */
function heldAbilities(Role $role): array
{
    return BouncerFacade::scope()->onceTo((int) $role->scope, fn () => $role->getAbilities()->pluck('name')->sort()->values()->all());
}

function presetCompany(): Company
{
    $company = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    app(CompanyService::class)->setupDefaults($company);

    return $company;
}

function presetMember(Company $company, string $roleName): User
{
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($company->id);
    BouncerFacade::scope()->onceTo($company->id, fn () => $member->assign($roleName));

    return $member;
}

test('a new company gets Owner, Manager and Read only with their exact abilities', function () {
    $company = presetCompany();
    $catalogue = collect(app(AbilityCatalog::class)->all())->pluck('ability')->sort()->values()->all();

    $owner = presetCopy($company, 'owner');
    $manager = presetCopy($company, 'preset:manager');
    $readOnly = presetCopy($company, 'preset:read-only');

    expect($owner->title)->toBe('Owner')
        ->and($manager->title)->toBe('Manager')
        ->and($readOnly->title)->toBe('Read only')
        ->and(heldAbilities($owner))->toBe($catalogue)
        ->and(heldAbilities($manager))->toHaveCount(41)
        ->not->toContain('create-custom-field', 'edit-exchange-rate-provider')
        ->and(heldAbilities($readOnly))->toHaveCount(13)
        ->and(collect(heldAbilities($readOnly))->every(fn ($a) => str_starts_with($a, 'view-') || $a === 'dashboard'))->toBeTrue();
});

test('syncing again changes nothing', function () {
    $service = app(RolePresetService::class);
    $roles = Role::query()->withoutGlobalScopes()->count();
    $permissions = DB::table('permissions')->count();

    $service->syncAll();
    $service->syncAll();

    expect(Role::query()->withoutGlobalScopes()->count())->toBe($roles)
        ->and(DB::table('permissions')->count())->toBe($permissions);
});

test('a Read only member can see invoices but not create them', function () {
    $member = presetMember($this->company, 'preset:read-only');
    Sanctum::actingAs($member, ['*']);
    $this->withHeaders(['company' => $this->company->id]);

    getJson('api/v1/invoices')->assertOk();

    BouncerFacade::scope()->to($this->company->id);
    expect($member->can('viewAny', Invoice::class))->toBeTrue()
        ->and($member->can('create', Invoice::class))->toBeFalse();
});

test('the grant writer writes into the role\'s own company when no scope is set', function () {
    $role = presetCopy($this->company, 'preset:read-only');

    BouncerFacade::scope()->removeOnce(fn () => app(RoleGrantWriter::class)->sync($role, ['view-invoice']));

    expect(heldAbilities($role))->toBe(['view-invoice'])
        ->and(DB::table('permissions')->where('entity_id', $role->id)->pluck('scope')->unique()->all())->toBe([$this->company->id])
        ->and(DB::table('abilities')->whereNull('scope')->count())->toBe(0);
});

test('the grant writer refuses a role that belongs to no company', function () {
    $role = BouncerFacade::scope()->removeOnce(fn () => Role::query()->create(['name' => 'nowhere']));

    app(RoleGrantWriter::class)->sync($role, ['view-invoice']);
})->throws(LogicException::class);

describe('the super administrator', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->admin, ['*']);
    });

    test('lists the presets with Owner first and locked', function () {
        getJson('api/v1/super-admin/role-presets')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'owner')
            ->assertJsonPath('data.0.is_owner', true)
            ->assertJsonPath('data.1.key', 'manager')
            ->assertJsonPath('data.2.key', 'read-only');
    });

    test('creates a preset that every company gets, with the abilities it depends on', function () {
        $other = presetCompany();

        postJson('api/v1/super-admin/role-presets', ['title' => 'Invoice clerk', 'abilities' => ['create-invoice']])
            ->assertCreated()
            ->assertJsonPath('data.key', 'invoice-clerk');

        foreach ([$this->company, $other] as $company) {
            $copy = presetCopy($company, 'preset:invoice-clerk');
            expect($copy)->not->toBeNull()
                ->and($copy->title)->toBe('Invoice clerk')
                ->and(heldAbilities($copy))->toContain('create-invoice', 'view-invoice', 'view-customer');
        }
    });

    test('never reuses a key or takes the owner one', function () {
        postJson('api/v1/super-admin/role-presets', ['title' => 'Owner!', 'abilities' => ['view-invoice']])
            ->assertCreated()->assertJsonPath('data.key', 'owner-preset');
        postJson('api/v1/super-admin/role-presets', ['title' => 'Clerk', 'abilities' => ['view-invoice']])
            ->assertJsonPath('data.key', 'clerk');
        RolePreset::query()->where('key', 'clerk')->update(['title' => 'Renamed clerk']);
        postJson('api/v1/super-admin/role-presets', ['title' => 'Clerk', 'abilities' => ['view-invoice']])
            ->assertJsonPath('data.key', 'clerk-2');

        $cyrillic = postJson('api/v1/super-admin/role-presets', ['title' => 'Бухгалтер', 'abilities' => ['view-invoice']])->json('data.key');
        expect(Str::startsWith($cyrillic, ['custom-', 'buxgalter']))->toBeTrue();
    });

    test('refuses unknown abilities and duplicate titles', function () {
        postJson('api/v1/super-admin/role-presets', ['title' => 'Bad', 'abilities' => ['fly-to-the-moon']])
            ->assertUnprocessable()->assertJsonValidationErrors('abilities.0');
        postJson('api/v1/super-admin/role-presets', ['title' => 'Manager', 'abilities' => ['view-invoice']])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
    });

    test('edits a preset and every company follows', function () {
        $manager = RolePreset::query()->where('key', 'manager')->firstOrFail();

        putJson("api/v1/super-admin/role-presets/{$manager->id}", ['title' => 'Team lead', 'abilities' => ['view-invoice']])
            ->assertOk();

        $copy = presetCopy($this->company, 'preset:manager');
        expect($copy->title)->toBe('Team lead')->and(heldAbilities($copy))->toBe(['view-invoice']);
    });

    test('cannot change or remove the Owner preset', function () {
        $owner = RolePreset::query()->where('key', 'owner')->firstOrFail();

        putJson("api/v1/super-admin/role-presets/{$owner->id}", ['title' => 'Boss', 'abilities' => ['view-invoice']])
            ->assertUnprocessable()->assertJsonPath('error', 'role_preset_locked');
        deleteJson("api/v1/super-admin/role-presets/{$owner->id}")
            ->assertUnprocessable()->assertJsonPath('error', 'role_preset_locked');
    });

    test('cannot remove a preset a member holds or an invitation offers', function () {
        $readOnly = RolePreset::query()->where('key', 'read-only')->firstOrFail();
        $member = presetMember($this->company, 'preset:read-only');

        deleteJson("api/v1/super-admin/role-presets/{$readOnly->id}")
            ->assertUnprocessable()->assertJsonPath('error', 'role_preset_in_use');

        // A member who left the company no longer counts; an invitation does.
        $member->companies()->detach($this->company->id);
        CompanyInvitation::query()->create([
            'company_id' => $this->company->id,
            'email' => 'invited@example.com',
            'role_id' => presetCopy($this->company, 'preset:read-only')->id,
            'token' => Str::random(40),
            'status' => CompanyInvitation::STATUS_PENDING,
            'invited_by' => $this->admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        deleteJson("api/v1/super-admin/role-presets/{$readOnly->id}")
            ->assertUnprocessable()->assertJsonPath('error', 'role_preset_in_use');

        CompanyInvitation::query()->delete();

        deleteJson("api/v1/super-admin/role-presets/{$readOnly->id}")->assertOk();
        expect(presetCopy($this->company, 'preset:read-only'))->toBeNull()
            ->and(RolePreset::query()->where('key', 'read-only')->exists())->toBeFalse();
    });

    test('reads the ability catalogue without a company', function () {
        getJson('api/v1/super-admin/abilities')->assertOk()->assertJsonCount(count(app(AbilityCatalog::class)->all()), 'abilities');
    });
});

test('only the super administrator reaches the preset endpoints', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'user']), ['*']);

    getJson('api/v1/super-admin/role-presets')->assertForbidden();
    postJson('api/v1/super-admin/role-presets', ['title' => 'Mine', 'abilities' => ['view-invoice']])->assertForbidden();
    getJson('api/v1/super-admin/abilities')->assertForbidden();
});

describe('a company owner', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->admin, ['*']);
        $this->withHeaders(['company' => $this->company->id]);
    });

    test('sees the presets marked, and cannot change or remove them', function () {
        $roles = collect(getJson('api/v1/roles')->assertOk()->json('data'))->keyBy('name');

        expect($roles['owner']['preset'])->toBe('owner')
            ->and($roles['preset:manager']['preset'])->toBe('manager');

        foreach (['owner', 'preset:manager'] as $name) {
            $id = $roles[$name]['id'];
            putJson("api/v1/roles/{$id}", ['name' => 'renamed', 'abilities' => [['ability' => 'view-invoice']]])->assertForbidden();
            deleteJson("api/v1/roles/{$id}")->assertForbidden();
        }
    });

    test('cannot make a role with a reserved name', function () {
        foreach (['owner', 'preset:clerk', 'Preset:Clerk'] as $name) {
            postJson('api/v1/roles', ['name' => $name, 'abilities' => [['ability' => 'view-invoice']]])
                ->assertUnprocessable()->assertJsonValidationErrors('name');
        }
    });

    test('can give a preset to a new member', function () {
        postJson('api/v1/members', [
            'name' => 'Preset Member',
            'email' => 'preset.member@example.com',
            'password' => 'long-enough-password',
            'companies' => [['id' => $this->company->id, 'role' => 'preset:read-only']],
        ])->assertCreated();
    });
});

test('the repair command restores a deleted copy and a revoked grant', function () {
    $copy = presetCopy($this->company, 'preset:read-only');
    BouncerFacade::scope()->onceTo($this->company->id, fn () => BouncerFacade::disallow($copy)->to('dashboard', null));
    $manager = presetCopy($this->company, 'preset:manager');
    BouncerFacade::scope()->onceTo($this->company->id, fn () => $manager->delete());

    $this->artisan('roles:sync-presets', ['--company' => $this->company->id])->assertSuccessful();

    expect(heldAbilities($copy))->toContain('dashboard')
        ->and(presetCopy($this->company, 'preset:manager'))->not->toBeNull();

    $this->artisan('roles:sync-presets', ['--company' => 999999])->assertFailed();
});

test('the upgrade gives existing companies the presets and leaves their own roles alone', function () {
    // A company from before presets: its own role, no preset copies, no preset rows.
    BouncerFacade::scope()->onceTo($this->company->id, function () {
        BouncerFacade::allow('bookkeeper')->to('view-expense', Expense::class);
    });
    Role::query()->withoutGlobalScopes()->where('name', 'like', 'preset:%')->get()
        ->each(fn (Role $role) => BouncerFacade::scope()->onceTo((int) $role->scope, fn () => $role->delete()));
    DB::table('role_presets')->delete();

    $migration = require database_path('migrations/2026_09_25_150000_create_role_presets_table.php');
    $migration->up();
    $migration->up();

    expect(RolePreset::query()->pluck('key')->sort()->values()->all())->toBe(['manager', 'owner', 'read-only'])
        ->and(presetCopy($this->company, 'preset:manager'))->not->toBeNull()
        ->and(presetCopy($this->company, 'preset:read-only'))->not->toBeNull()
        ->and(heldAbilities(presetCopy($this->company, 'bookkeeper')))->toBe(['view-expense']);
});

test('enabling a module hands its abilities to presets that list them', function () {
    Registry::flush();
    Registry::registerAbility('preset-probe', ['ability' => 'view-thing', 'name' => 'View things']);

    $preset = app(RolePresetService::class)->create('Thing viewer', ['preset-probe:view-thing']);
    $copy = presetCopy($this->company, $preset->roleName());
    BouncerFacade::scope()->onceTo($this->company->id, fn () => BouncerFacade::disallow($copy)->to('preset-probe:view-thing', null));
    BouncerFacade::refresh();
    expect(heldAbilities($copy))->not->toContain('preset-probe:view-thing');

    $sync = app(ModuleAbilitySync::class);
    $sync->grant('preset-probe', Registry::abilitiesFor('preset-probe'));

    expect(heldAbilities($copy))->toContain('preset-probe:view-thing')
        ->and(heldAbilities(presetCopy($this->company, 'preset:read-only')))->not->toContain('preset-probe:view-thing');

    Registry::flush();
});
