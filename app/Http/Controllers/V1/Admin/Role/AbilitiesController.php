<?php

namespace App\Http\Controllers\V1\Admin\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AbilitiesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response()->json(['abilities' => config('abilities.abilities')]);
    }
}
