<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Space\TimeZones;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimezonesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'time_zones' => TimeZones::get_list(),
        ]);
    }
}
