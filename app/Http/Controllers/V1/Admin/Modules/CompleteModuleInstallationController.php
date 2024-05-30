<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Space\ModuleInstaller;
use Illuminate\Http\Request;

class CompleteModuleInstallationController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::complete($request->module, $request->version);

        return response()->json([
            'success' => $response,
        ]);
    }
}
