<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Services\EstimateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EstimatePdfController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
    ) {}

    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, Estimate $estimate)
    {
        if ($request->has('preview')) {
            return $this->estimateService->getPdfData($estimate);
        }

        return $estimate->getGeneratedPDFOrStream('estimate');
    }
}
