<?php

namespace App\Http\Controllers\V1\Admin\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AbilitiesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        return response()->json(['abilities' => config('abilities.abilities')]);
    }
}
