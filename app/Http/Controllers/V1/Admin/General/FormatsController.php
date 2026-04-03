<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Support\Formatters\DateFormatter;
use App\Support\Formatters\TimeFormatter;
use App\Support\Formatters\TimeZones;
use Illuminate\Http\JsonResponse;

class FormatsController extends Controller
{
    public function dateFormats(): JsonResponse
    {
        return response()->json([
            'date_formats' => DateFormatter::get_list(),
        ]);
    }

    public function timeFormats(): JsonResponse
    {
        return response()->json([
            'time_formats' => TimeFormatter::get_list(),
        ]);
    }

    public function timezones(): JsonResponse
    {
        return response()->json([
            'time_zones' => TimeZones::get_list(),
        ]);
    }
}
