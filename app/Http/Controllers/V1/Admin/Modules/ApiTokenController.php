<?php

namespace App\Http\Controllers\V1\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Space\ModuleInstaller;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $this->authorize('manage modules');

        $response = ModuleInstaller::checkToken($request->api_token);

        return $response;
    }
}
