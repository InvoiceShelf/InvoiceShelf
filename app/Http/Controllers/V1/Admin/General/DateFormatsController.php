<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Space\DateFormatter;
use Illuminate\Http\Request;

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
