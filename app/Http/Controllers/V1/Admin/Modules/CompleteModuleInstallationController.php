<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Space\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompleteModuleInstallationController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $this->authorize('manage modules');

        $request->validate([
            'catalog_kind' => ['nullable', 'in:module,pdf_template'],
        ]);

        try {
            $response = ModuleInstaller::complete(
                $request->module,
                $request->version,
                (string) $request->input('catalog_kind', 'module'),
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => $response,
            'post_install' => ModuleInstaller::postInstallHints(
                (string) $request->module,
                (string) $request->input('catalog_kind', 'module'),
            ),
        ]);
    }
}
