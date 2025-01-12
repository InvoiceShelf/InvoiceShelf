<?php

namespace App\Http\Controllers\V1\Installation;

use App\Http\Controllers\Controller;
use App\Http\Requests\DomainEnvironmentRequest;
use App\Space\EnvironmentManager;
use Illuminate\Support\Facades\Artisan;

class AppDomainController extends Controller
{
    public function __invoke(DomainEnvironmentRequest $request)
    {
        Artisan::call('optimize:clear');

        $environmentManager = new EnvironmentManager;

        $results = $environmentManager->saveDomainVariables($request);

        if (in_array('error', $results)) {
            return response()->json($results);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
