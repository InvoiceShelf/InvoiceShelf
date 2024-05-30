<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Http\Request;

class EstimateTemplatesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        $estimateTemplates = Estimate::estimateTemplates();

        return response()->json([
            'estimateTemplates' => $estimateTemplates,
        ]);
    }
}
