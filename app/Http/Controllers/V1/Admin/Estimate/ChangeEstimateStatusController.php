<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChangeEstimateStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $estimate->update($request->only('status'));

        return response()->json([
            'success' => true,
        ]);
    }
}
