<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Modules\Events\ModuleDisabledEvent;
use App\Platform\Modules\Events\ModuleEnabledEvent;
use App\Platform\Modules\Models\Module;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ModuleBundleFixture;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $user = User::findOrFail(1);
    $this->withHeader('company', $user->companies()->firstOrFail()->id);
    Sanctum::actingAs($user, ['*']);
});

it('disables a module when its runtime files are missing', function () {
    Event::fake([ModuleDisabledEvent::class]);
    $module = missingRuntimeModule(enabled: true);

    postJson("/api/v1/modules/{$module->name}/disable")
        ->assertOk()
        ->assertJsonPath('success', true);

    $module->refresh();

    expect($module->installed)->toBeFalse()
        ->and($module->enabled)->toBeFalse()
        ->and($module->state)->toBe('failed')
        ->and($module->last_error)->toBe('module_runtime_missing')
        ->and($module->last_failed_at)->not->toBeNull();

    Event::assertDispatched(ModuleDisabledEvent::class);
});

it('returns a conflict and repairs state when enabling a module with missing runtime files', function () {
    Event::fake([ModuleEnabledEvent::class]);
    $module = missingRuntimeModule(enabled: false);

    postJson("/api/v1/modules/{$module->name}/enable")
        ->assertConflict()
        ->assertJson([
            'success' => false,
            'error' => 'module_runtime_missing',
        ]);

    $module->refresh();

    expect($module->installed)->toBeFalse()
        ->and($module->enabled)->toBeFalse()
        ->and($module->state)->toBe('failed')
        ->and($module->last_error)->toBe('module_runtime_missing')
        ->and($module->last_failed_at)->not->toBeNull();

    Event::assertNotDispatched(ModuleEnabledEvent::class);
});

function missingRuntimeModule(bool $enabled): Module
{
    return Module::query()->create([
        'name' => 'DefinitelyMissingRuntime',
        'slug' => 'definitely-missing-runtime',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => $enabled,
        'state' => 'installed',
    ]);
}

it('refuses to enable a module whose bundle no longer fits this host', function () {
    Event::fake([ModuleEnabledEvent::class]);
    ModuleBundleFixture::write(base_path('Modules/EnableProbe'), ['sample/enable-probe' => []], ['laravel/framework' => ['sample/enable-probe' => '^9.0']]);
    $module = Module::query()->create(['name' => 'EnableProbe', 'slug' => 'enable-probe', 'version' => '1.0.0', 'installed' => true, 'enabled' => false, 'state' => 'installed']);

    try {
        postJson("/api/v1/modules/{$module->name}/enable")
            ->assertConflict()
            ->assertJsonPath('error', 'module_incompatible')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'needs laravel/framework ^9.0'));

        expect($module->fresh()->enabled)->toBeFalse();
        Event::assertNotDispatched(ModuleEnabledEvent::class);
    } finally {
        File::deleteDirectory(base_path('Modules/EnableProbe'));
    }
});
