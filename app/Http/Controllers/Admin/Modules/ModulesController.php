<?php

namespace App\Http\Controllers\Admin\Modules;

use App\Events\ModuleDisabledEvent;
use App\Events\ModuleEnabledEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Models\Module as ModelsModule;
use App\Services\Module\ModuleInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nwidart\Modules\Facades\Module;

class ModulesController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::getModules();

        if (($response['status'] ?? 0) !== 200 || ! isset($response['body']->modules)) {
            return response()->json(['error' => 'marketplace_unavailable'], 503);
        }

        return ModuleResource::collection(collect($response['body']->modules));
    }

    public function show(Request $request, string $module)
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::getModule($module);

        if (($response['status'] ?? 0) === 404) {
            return response()->json(['error' => 'not_found'], 404);
        }

        if (($response['status'] ?? 0) !== 200 || ! isset($response['body']->data)) {
            return response()->json(['error' => 'marketplace_unavailable'], 503);
        }

        return (new ModuleResource($response['body']->data))
            ->additional(['meta' => [
                'modules' => ModuleResource::collection(
                    collect($response['body']->meta->modules ?? [])
                ),
            ]]);
    }

    public function checkToken(Request $request): JsonResponse
    {
        $this->authorize('manage modules');

        return ModuleInstaller::checkToken($request->api_token);
    }

    public function enable(Request $request, string $module): JsonResponse
    {
        $this->authorize('manage modules');

        $module = ModelsModule::where('name', $module)->first();
        $module->update(['enabled' => true]);
        $installedModule = Module::find($module->name);
        $installedModule->enable();

        ModuleEnabledEvent::dispatch($module);

        return response()->json(['success' => true]);
    }

    public function disable(Request $request, string $module): JsonResponse
    {
        $this->authorize('manage modules');

        $module = ModelsModule::where('name', $module)->first();
        $module->update(['enabled' => false]);
        $installedModule = Module::find($module->name);
        $installedModule->disable();

        ModuleDisabledEvent::dispatch($module);

        return response()->json(['success' => true]);
    }
}
