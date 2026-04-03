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

        return ModuleInstaller::getModules();
    }

    public function show(Request $request, string $module)
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::getModule($module);

        if (! $response->success) {
            return response()->json($response);
        }

        return (new ModuleResource($response->module))
            ->additional(['meta' => [
                'modules' => ModuleResource::collection(collect($response->modules)),
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
