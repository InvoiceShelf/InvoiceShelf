<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            $request->key => config('invoiceshelf.'.$request->key),
        ]);
    }
}
