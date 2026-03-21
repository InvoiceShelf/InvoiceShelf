<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Space\DateFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DateFormatsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'date_formats' => DateFormatter::get_list(),
        ]);
    }
}
