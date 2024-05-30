<?php

namespace App\Http\Controllers\V1\Admin\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\SerialNumberFormatter;

class NumberPlaceholdersController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        if ($request->format) {
            $placeholders = SerialNumberFormatter::getPlaceholders($request->format);
        } else {
            $placeholders = [];
        }

        return response()->json([
            'success' => true,
            'placeholders' => $placeholders,
        ]);
    }
}
