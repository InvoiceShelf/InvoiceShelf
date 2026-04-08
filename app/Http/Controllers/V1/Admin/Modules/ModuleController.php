<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Space\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ModuleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, string $module)
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::getModule($module);

        if (! $response->success) {
            $status = ($response->error ?? '') === 'catalog_unavailable' ? 503 : 404;

            return response()->json($response, $status);
        }

        return (new ModuleResource($response->module))
            ->additional(['meta' => [
                'modules' => ModuleResource::collection(collect($response->modules)),
            ]]);
    }
}
