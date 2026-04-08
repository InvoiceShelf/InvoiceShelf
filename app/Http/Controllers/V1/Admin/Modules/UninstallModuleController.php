<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Space\ModuleInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UninstallModuleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $module): JsonResponse
    {
        $this->authorize('manage modules');

        $result = ModuleInstaller::uninstall($module);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json(array_merge($result, [
            'post_install' => ModuleInstaller::postInstallHints($module, 'module'),
        ]));
    }
}
