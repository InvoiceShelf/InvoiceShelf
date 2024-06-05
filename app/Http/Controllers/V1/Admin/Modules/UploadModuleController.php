<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadModuleRequest;
use App\Space\ModuleInstaller;

class UploadModuleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(UploadModuleRequest $request)
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::upload($request);

        return response()->json($response);
    }
}
