<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Space\ModuleInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunPostInstallCommandsController extends Controller
{
    public function __invoke(Request $request, string $module): JsonResponse
    {
        $this->authorize('manage modules');

        $request->validate([
            'catalog_kind' => ['nullable', 'in:module,pdf_template'],
        ]);

        $result = ModuleInstaller::runPostInstallCommands(
            $module,
            (string) $request->input('catalog_kind', 'module'),
        );

        return response()->json($result);
    }
}
