<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnzipUpdateRequest;
use App\Space\ModuleInstaller;
use Illuminate\Http\Response;

class UnzipModuleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(UnzipUpdateRequest $request)
    {
        $this->authorize('manage modules');

        try {
            $path = ModuleInstaller::unzip($request->module, $request->path);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
        ]);
    }
}
