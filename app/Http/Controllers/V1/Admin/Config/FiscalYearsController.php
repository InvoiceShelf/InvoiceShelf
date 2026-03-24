<?php

namespace App\Http\Controllers\V1\Admin\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FiscalYearsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'fiscal_years' => config('invoiceshelf.fiscal_years'),
        ]);
    }
}
