<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Events\ModuleDisabledEvent;
use App\Http\Controllers\Controller;
use App\Models\Module as ModelsModule;
use Illuminate\Http\Request;
use Nwidart\Modules\Facades\Module;

class DisableModuleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, string $module)
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
