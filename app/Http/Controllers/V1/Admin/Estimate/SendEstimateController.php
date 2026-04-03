<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendEstimatesRequest;
use App\Models\Estimate;
use App\Services\EstimateService;
use Illuminate\Http\JsonResponse;

class SendEstimateController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
    ) {}

    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $response = $this->estimateService->send($estimate, $request->all());

        return response()->json($response);
    }
}
