<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Events\ModuleEnabledEvent;
use App\Http\Controllers\Controller;
use App\Models\Module as ModelsModule;
use Illuminate\Http\Request;
use Nwidart\Modules\Facades\Module;

class EnableModuleController extends Controller
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
        $module->update(['enabled' => true]);
        $installedModule = Module::find($module->name);
        $installedModule->enable();

        ModuleEnabledEvent::dispatch($module);

        return response()->json(['success' => true]);
    }
}
