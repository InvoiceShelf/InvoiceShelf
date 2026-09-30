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
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
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
        ->and(heldAbilities($manager))->toHaveCount(65)
        ->not->toContain('create-custom-field', 'edit-exchange-rate-provider')
        ->and(heldAbilities($readOnly))->toHaveCount(19)
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

/**
 * List an ability in config/abilities.php for the given presets, as a release
 * that ships a new default would.
 *
 * @param  list<string>  $presets
 */
function tagAbilityForPresets(string $ability, array $presets): void
{
    config()->set('abilities.abilities', array_map(
        fn (array $entry) => $entry['ability'] === $ability ? [...$entry, 'presets' => $presets] : $entry,
        config('abilities.abilities'),
    ));
}

test('the shipped presets start with every current default already applied', function () {
    $manager = RolePreset::query()->where('key', 'manager')->firstOrFail();

    expect(app(RolePresetService::class)->applyDefaults())->toBe([])
        ->and($manager->applied_defaults)->toBe($manager->abilities)
        ->and(heldAbilities(presetCopy($this->company, 'preset:manager')))->toHaveCount(65);
});

test('a newly tagged ability reaches the preset and every company copy once', function () {
    $other = presetCompany();
    tagAbilityForPresets('create-custom-field', ['manager', 'owner']);

    expect(app(RolePresetService::class)->applyDefaults())->toBe(['manager' => ['create-custom-field']])
        ->and(RolePreset::query()->where('key', 'manager')->value('abilities'))->toContain('create-custom-field')
        ->and(heldAbilities(presetCopy($this->company, 'preset:manager')))->toContain('create-custom-field')
        ->and(heldAbilities(presetCopy($other, 'preset:manager')))->toContain('create-custom-field')
        ->and(heldAbilities(presetCopy($this->company, 'preset:read-only')))->not->toContain('create-custom-field');

    $permissions = DB::table('permissions')->count();

    expect(app(RolePresetService::class)->applyDefaults())->toBe([])
        ->and(DB::table('permissions')->count())->toBe($permissions);
});

test('a default the super administrator took away is not handed back', function () {
    $service = app(RolePresetService::class);
    tagAbilityForPresets('create-custom-field', ['manager']);
    $service->applyDefaults();

    $manager = RolePreset::query()->where('key', 'manager')->firstOrFail();
    $service->update($manager, $manager->title, array_values(array_diff($manager->abilities, ['create-custom-field'])));

    expect($service->applyDefaults())->toBe([])
        ->and($manager->fresh()->abilities)->not->toContain('create-custom-field')
        ->and(heldAbilities(presetCopy($this->company, 'preset:manager')))->not->toContain('create-custom-field');
});

test('a deleted preset is not made again for a new default', function () {
    $service = app(RolePresetService::class);
    $service->delete(RolePreset::query()->where('key', 'read-only')->firstOrFail());
    tagAbilityForPresets('view-custom-field', ['manager', 'read-only']);
    tagAbilityForPresets('create-custom-field', ['read-only']);

    expect($service->applyDefaults())->toBe([])
        ->and(RolePreset::query()->where('key', 'read-only')->exists())->toBeFalse()
        ->and(presetCopy($this->company, 'preset:read-only'))->toBeNull();
});

test('a new default brings the abilities it depends on', function () {
    tagAbilityForPresets('create-custom-field', ['read-only']);
    tagAbilityForPresets('create-exchange-rate-provider', ['read-only']);

    app(RolePresetService::class)->applyDefaults();

    expect(RolePreset::query()->where('key', 'read-only')->value('abilities'))
        ->toContain('create-custom-field', 'view-custom-field', 'create-exchange-rate-provider', 'view-exchange-rate-provider');
});

test('migrating applies new defaults, and the command reports them', function () {
    tagAbilityForPresets('create-custom-field', ['manager']);

    event(new NoPendingMigrations('up'));

    expect(heldAbilities(presetCopy($this->company, 'preset:manager')))->toContain('create-custom-field');

    tagAbilityForPresets('edit-custom-field', ['manager']);
    event(new MigrationsEnded('down'));
    event(new MigrationsEnded('up', ['pretend' => true]));

    expect(heldAbilities(presetCopy($this->company, 'preset:manager')))->not->toContain('edit-custom-field');

    $this->artisan('roles:apply-preset-defaults')
        ->expectsOutputToContain('manager: edit-custom-field')
        ->assertSuccessful();
    $this->artisan('roles:apply-preset-defaults')
        ->expectsOutputToContain('Every preset already has its defaults.')
        ->assertSuccessful();
});

test('the upgrade marks what Manager and Read only were seeded with, whatever the catalogue says now', function () {
    DB::table('role_presets')->whereIn('key', ['manager', 'read-only'])->update(['applied_defaults' => null]);
    tagAbilityForPresets('create-custom-field', ['manager']);

    $migration = require database_path('migrations/2026_09_26_100000_add_applied_defaults_to_role_presets_table.php');
    $migration->up();
    $migration->up();

    expect(RolePreset::query()->where('key', 'manager')->value('applied_defaults'))->toHaveCount(41)
        ->not->toContain('create-custom-field')
        ->and(RolePreset::query()->where('key', 'read-only')->value('applied_defaults'))->toHaveCount(13);

    // Everything tagged after the seeded lists is offered, the probe included.
    $offered = app(RolePresetService::class)->applyDefaults();

    expect($offered['manager'])->toContain('create-custom-field', 'view-bill', 'delete-supplier-refund')
        ->not->toContain('view-customer')
        ->and($offered['read-only'])->toContain('view-bill')->not->toContain('create-bill');
});

test('a new ability reaches every owner role without a migration', function () {
    $other = presetCompany();
    config()->push('abilities.abilities', ['name' => 'view probe', 'ability' => 'view-probe', 'model' => null]);

    expect(app(RolePresetService::class)->applyDefaults())->toBe(['owner' => ['view-probe']])
        ->and(heldAbilities(presetCopy($this->company, 'owner')))->toContain('view-probe')
        ->and(heldAbilities(presetCopy($other, 'owner')))->toContain('view-probe')
        ->and(heldAbilities(presetCopy($this->company, 'preset:manager')))->not->toContain('view-probe')
        ->and(RolePreset::query()->where('key', 'owner')->value('abilities'))->toBeNull()
        ->and(app(RolePresetService::class)->applyDefaults())->toBe([]);
});

test('the first run after the upgrade tops up every owner role once', function () {
    DB::table('role_presets')->where('key', 'owner')->update(['applied_defaults' => null]);
    $owner = presetCopy($this->company, 'owner');
    BouncerFacade::scope()->onceTo($this->company->id, fn () => BouncerFacade::disallow($owner)->to('view-invoice', Invoice::class));
    BouncerFacade::refresh();

    $offered = app(RolePresetService::class)->applyDefaults();
    $catalogue = collect(app(AbilityCatalog::class)->all())->pluck('ability')->sort()->values()->all();

    expect(array_keys($offered))->toBe(['owner'])
        ->and(heldAbilities($owner))->toBe($catalogue)
        ->and(app(RolePresetService::class)->applyDefaults())->toBe([]);
});
