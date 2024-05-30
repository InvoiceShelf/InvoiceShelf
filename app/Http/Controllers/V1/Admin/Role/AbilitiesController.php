<?php

namespace App\Http\Controllers\V1\Admin\Role;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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
