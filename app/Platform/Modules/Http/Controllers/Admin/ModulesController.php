<?php

namespace App\Platform\Modules\Http\Controllers\Admin;

use App\Platform\Http\Controller;
use App\Platform\Modules\Events\ModuleDisabledEvent;
use App\Platform\Modules\Events\ModuleEnabledEvent;
use App\Platform\Modules\Http\Resources\ModuleResource;
use App\Platform\Modules\Marketplace\MarketplaceClient;
use App\Platform\Modules\Models\Module as ModelsModule;
use App\Platform\Modules\Runtime\DatabaseActivator;
use App\Platform\Modules\Runtime\ModuleBundle;
use App\Platform\Modules\Runtime\ModuleCompatibility;
use Illuminate\Http\JsonResponse;
use Nwidart\Modules\Facades\Module;

class ModulesController extends Controller
{
    public function index(MarketplaceClient $client)
    {
        $this->authorize('manage modules');

        $response = $client->catalog();
        $body = $response->json();
        $modules = is_array($body) ? ($body['modules'] ?? $body['data'] ?? null) : null;

        if (! $response->successful() || ! is_array($modules)) {
            return response()->json(['error' => 'marketplace_unavailable'], 503);
        }

        return ModuleResource::collection(collect($modules));
    }

    public function show(string $module, MarketplaceClient $client)
    {
        $this->authorize('manage modules');

        $response = $client->module($module);
        $body = $response->json();

        if ($response->status() === 404) {
            return response()->json(['error' => 'not_found'], 404);
        }

        if (! $response->successful() || ! is_array($body) || ! is_array($body['module'] ?? null)) {
            return response()->json(['error' => 'marketplace_unavailable'], 503);
        }

        return (new ModuleResource($body['module']))
            ->additional(['meta' => [
                'modules' => ModuleResource::collection(
                    collect($body['meta']['modules'] ?? [])
                ),
            ]]);
    }

    public function enable(string $module): JsonResponse
    {
        $this->authorize('manage modules');

        $module = ModelsModule::query()
            ->where('name', $module)
            ->where('installed', true)
            ->firstOrFail();
        $installedModule = Module::find($module->name);

        if ($installedModule === null) {
            $this->markRuntimeMissing($module);

            return response()->json([
                'success' => false,
                'error' => 'module_runtime_missing',
            ], 409);
        }

        // modules:reconcile disables a module that stopped fitting this host;
        // switching it back on must not skip the same checks.
        $problems = [
            ...ModuleCompatibility::problems($installedModule->get('compatibility')),
            ...ModuleBundle::problemsFor($installedModule->getPath()),
        ];
        if ($problems !== []) {
            return response()->json([
                'success' => false,
                'error' => 'module_incompatible',
                'message' => implode(' ', $problems),
            ], 409);
        }

        $installedModule->enable();
        $module->refresh();

        event(new ModuleEnabledEvent($module));

        return new JsonResponse(['success' => true]);
    }

    public function disable(string $module, DatabaseActivator $activator): JsonResponse
    {
        $this->authorize('manage modules');

        $module = ModelsModule::query()
            ->where('name', $module)
            ->where('installed', true)
            ->firstOrFail();

        $activator->setActiveByName($module->name, false);

        if (Module::find($module->name) === null) {
            $this->markRuntimeMissing($module);
        } else {
            $module->refresh();
        }

        ModuleDisabledEvent::dispatch($module);

        return response()->json(['success' => true]);
    }

    private function markRuntimeMissing(ModelsModule $module): void
    {
        $module->update([
            'installed' => false,
            'enabled' => false,
            'state' => 'failed',
            'last_error' => 'module_runtime_missing',
            'last_failed_at' => now(),
        ]);
    }
}
