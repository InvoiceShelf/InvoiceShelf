<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Space\TimeFormatter;
use Illuminate\Http\Request;

class TimeFormatsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'time_formats' => TimeFormatter::get_list(),
        ]);
    }
}
