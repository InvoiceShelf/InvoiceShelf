<?php

namespace App\Http\Controllers\V1\Admin\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Space\TimeZones;

class TimezonesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'time_zones' => TimeZones::get_list(),
        ]);
    }
}
