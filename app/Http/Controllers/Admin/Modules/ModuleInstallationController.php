<?php

namespace App\Http\Controllers\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnzipUpdateRequest;
use App\Http\Requests\UploadModuleRequest;
use App\Services\Module\ModuleInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleInstallationController extends Controller
{
    public function download(Request $request): JsonResponse
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::download($request->module, $request->version);

        return response()->json($response);
    }

    public function upload(UploadModuleRequest $request): JsonResponse
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::upload($request);

        return response()->json($response);
    }

    public function unzip(UnzipUpdateRequest $request): JsonResponse
    {
        $this->authorize('manage modules');

        $path = ModuleInstaller::unzip($request->module, $request->path);

        return response()->json([
            'success' => true,
            'path' => $path,
        ]);
    }

    public function copy(Request $request): JsonResponse
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::copyFiles($request->module, $request->path);

        return response()->json([
            'success' => $response,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::complete($request->module, $request->version);

        return response()->json([
            'success' => $response,
        ]);
    }
}
