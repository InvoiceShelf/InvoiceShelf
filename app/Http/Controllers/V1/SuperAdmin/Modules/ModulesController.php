<?php

namespace App\Http\Controllers\V1\SuperAdmin\Modules;

use App\Http\Controllers\Controller;
use App\Services\Module\ModuleInstaller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ModulesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::getModules();

        return $response;
    }
}
