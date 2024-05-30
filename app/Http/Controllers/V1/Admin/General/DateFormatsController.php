<?php

namespace App\Http\Controllers\V1\Admin\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Space\DateFormatter;

class DateFormatsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'date_formats' => DateFormatter::get_list(),
        ]);
    }
}
