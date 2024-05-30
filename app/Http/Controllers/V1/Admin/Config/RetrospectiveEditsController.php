<?php

namespace App\Http\Controllers\V1\Admin\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RetrospectiveEditsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'retrospective_edits' => config('invoiceshelf.retrospective_edits'),
        ]);
    }
}
