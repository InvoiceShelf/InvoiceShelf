<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Platform\Modules\Events\ModuleEnabledEvent;
use App\Platform\Modules\Models\Module;
use App\Platform\Modules\Runtime\ModuleInstaller;
use App\Platform\Modules\Runtime\ModuleRuntimeAutoloader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvoiceShelf\Modules\Registry;
use Laravel\Sanctum\Sanctum;
use Nwidart\Modules\Facades\Module as RuntimeModule;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Models as BouncerModels;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $user = User::findOrFail(1);
    $this->firstCompanyId = $user->companies()->firstOrFail()->id;
    $this->withHeader('company', $this->firstCompanyId);
    Sanctum::actingAs($user, ['*']);

    $second = Company::factory()->create();
    app(CompanyService::class)->setupDefaults($second);
    $this->secondCompanyId = $second->id;

    Registry::flush();
});

afterEach(function () {
    Registry::flush();
    File::deleteDirectory(base_path('Modules/AbilitySync'));
    File::deleteDirectory(base_path('Modules/.backups'));
});

it('grants a module ability to every company owner when the module is enabled', function () {
    $module = abilitySyncModule();

    expect(Registry::abilitiesFor('sync-probe'))->toBe([]);

    postJson("/api/v1/modules/{$module->name}/enable")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Registry::abilitiesFor('sync-probe'))->toHaveCount(1)
        ->and(syncOwnerAbilities($this->firstCompanyId))->toContain('sync-probe:view-thing')
        ->and(syncOwnerAbilities($this->secondCompanyId))->toContain('sync-probe:view-thing');
});

it('changes nothing when an already enabled module is enabled again', function () {
    $module = abilitySyncModule();

    postJson("/api/v1/modules/{$module->name}/enable")->assertOk();
    expect(syncPermissionCount())->toBe(2);

    postJson("/api/v1/modules/{$module->name}/enable")->assertOk();

    expect(syncPermissionCount())->toBe(2)
        ->and(syncAbilityCount())->toBe(2);
});

it('leaves the grants in place when the module is only disabled', function () {
    $module = abilitySyncModule();
    postJson("/api/v1/modules/{$module->name}/enable")->assertOk();

    postJson("/api/v1/modules/{$module->name}/disable")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(syncOwnerAbilities($this->firstCompanyId))->toContain('sync-probe:view-thing')
        ->and(syncOwnerAbilities($this->secondCompanyId))->toContain('sync-probe:view-thing');
});

it('removes the ability and permission rows when the module is uninstalled', function () {
    $module = abilitySyncModule();
    postJson("/api/v1/modules/{$module->name}/enable")->assertOk();

    postJson("/api/v1/modules/{$module->name}/uninstall", ['remove_data' => false])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(syncAbilityCount())->toBe(0)
        ->and(syncPermissionCount())->toBe(0)
        ->and(syncOwnerAbilities($this->firstCompanyId))->not->toContain('sync-probe:view-thing');
});

it('falls back to the manifest slug when the registry row has none', function () {
    $module = abilitySyncModule(slug: null);

    postJson("/api/v1/modules/{$module->name}/enable")->assertOk();

    expect(syncOwnerAbilities($this->firstCompanyId))->toContain('sync-probe:view-thing');
});

it('re-grants an enabled module whose permissions were lost', function () {
    $module = abilitySyncModule();
    postJson("/api/v1/modules/{$module->name}/enable")->assertOk();

    DB::table('permissions')->whereIn('ability_id', syncAbilityIds())->delete();
    expect(syncPermissionCount())->toBe(0);

    expect(Artisan::call('modules:sync-abilities'))->toBe(0);

    expect(syncPermissionCount())->toBe(2)
        ->and(syncOwnerAbilities($this->secondCompanyId))->toContain('sync-probe:view-thing');
});

it('clears the abilities of a module the command finds uninstalled', function () {
    $module = abilitySyncModule();
    postJson("/api/v1/modules/{$module->name}/enable")->assertOk();

    $module->update(['installed' => false, 'enabled' => false, 'state' => 'uninstalled']);

    expect(Artisan::call('modules:sync-abilities', ['module' => 'AbilitySync']))->toBe(0);

    expect(syncAbilityCount())->toBe(0)
        ->and(syncPermissionCount())->toBe(0);
});

it('enables and boots a module before the installer announces it', function () {
    abilitySyncFiles();
    $seen = [];

    // The ability listener is dropped on purpose: it registers the runtime
    // itself, so leaving it in place would answer the question being asked.
    Event::forget(ModuleEnabledEvent::class);
    Event::listen(ModuleEnabledEvent::class, function (ModuleEnabledEvent $event) use (&$seen): void {
        $seen['enabled'] = RuntimeModule::find('AbilitySync')?->isEnabled();
        $seen['abilities'] = Registry::abilitiesFor('sync-probe');
        $seen['slug'] = $event->module->slug;
    });

    expect(ModuleInstaller::complete('AbilitySync', '1.0.0'))->toBeTrue();

    expect($seen['enabled'])->toBeTrue()
        ->and($seen['abilities'])->toHaveCount(1)
        ->and($seen['slug'])->toBe('sync-probe');
});

it('runs the migrations of a module that is disabled on disk when installing it', function () {
    abilitySyncFiles();

    expect(Schema::hasTable('ability_sync_probe'))->toBeFalse();
    expect(ModuleInstaller::complete('AbilitySync', '1.0.0'))->toBeTrue();

    expect(Schema::hasTable('ability_sync_probe'))->toBeTrue()
        ->and(DB::table('migrations')->where('migration', '2026_01_01_000000_create_ability_sync_probe_table')->exists())->toBeTrue();
});

it('writes the manifest slug and grants through the installer path', function () {
    abilitySyncFiles();

    expect(ModuleInstaller::complete('AbilitySync', '1.0.0'))->toBeTrue();

    expect(Module::query()->where('name', 'AbilitySync')->value('slug'))->toBe('sync-probe')
        ->and(syncOwnerAbilities($this->firstCompanyId))->toContain('sync-probe:view-thing')
        ->and(syncOwnerAbilities($this->secondCompanyId))->toContain('sync-probe:view-thing');
});

/**
 * Write the fixture module to disk and make its classes loadable.
 *
 * Deliberately no call to the module repository's `register()`: the point of
 * these tests is that the provider has not booted yet when the module is
 * switched on, which is exactly the situation the listener has to cope with.
 */
function abilitySyncFiles(): void
{
    $path = base_path('Modules/AbilitySync');
    File::ensureDirectoryExists($path.'/app/Providers');
    File::ensureDirectoryExists($path.'/database/migrations');

    File::put($path.'/module.json', json_encode([
        'name' => 'AbilitySync',
        'alias' => 'ability_sync',
        'description' => 'Ability sync test fixture',
        'keywords' => [],
        'priority' => 0,
        'providers' => ['Modules\\AbilitySync\\Providers\\AbilitySyncServiceProvider'],
        'aliases' => [],
        'files' => [],
        'requires' => [],
        'schema_version' => 1,
        'slug' => 'sync-probe',
        'version' => '1.0.0',
        'license' => 'AGPL-3.0-only',
        'compatibility' => ['invoiceshelf' => '^3.0.0', 'module_api' => '^1.1.0', 'php' => '^8.4.0', 'extensions' => []],
        'module_dependencies' => [],
        'migration_policy' => 'forward-only',
        'dependency_policy' => 'host-provided-only',
        'assets' => [],
    ], JSON_THROW_ON_ERROR));

    File::put($path.'/app/Providers/AbilitySyncServiceProvider.php', <<<'PHP'
<?php

namespace Modules\AbilitySync\Providers;

use Illuminate\Support\ServiceProvider;
use InvoiceShelf\Modules\Registry;

class AbilitySyncServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Registry::registerAbility('sync-probe', [
            'ability' => 'view-thing',
            'name' => 'View things',
        ]);
    }
}
PHP);

    File::put($path.'/database/migrations/2026_01_01_000000_create_ability_sync_probe_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ability_sync_probe', function (Blueprint $table): void {
            $table->bigIncrements('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ability_sync_probe');
    }
};
PHP);

    ModuleRuntimeAutoloader::register('AbilitySync');
}

/**
 * The fixture on disk plus an installed-but-switched-off registry row.
 */
function abilitySyncModule(?string $slug = 'sync-probe'): Module
{
    abilitySyncFiles();

    return Module::query()->updateOrCreate(['name' => 'AbilitySync'], [
        'slug' => $slug,
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => false,
        'state' => 'installed',
    ]);
}

/**
 * Ids of every ability row the fixture module owns, across all scopes.
 *
 * @return list<int>
 */
function syncAbilityIds(): array
{
    return DB::table('abilities')->where('name', 'like', 'sync-probe:%')->pluck('id')->all();
}

function syncAbilityCount(): int
{
    return count(syncAbilityIds());
}

function syncPermissionCount(): int
{
    $ids = syncAbilityIds();

    return $ids === [] ? 0 : DB::table('permissions')->whereIn('ability_id', $ids)->count();
}

/**
 * Every ability name held by a company's `owner` role.
 *
 * @return list<string>
 */
function syncOwnerAbilities(int $companyId): array
{
    return BouncerFacade::scope()->onceTo($companyId, function () use ($companyId): array {
        $role = BouncerModels::role()->newQuery()
            ->where('name', 'owner')
            ->where('scope', $companyId)
            ->first();

        return $role === null ? [] : $role->abilities()->pluck('name')->all();
    });
}
