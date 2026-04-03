<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEstimatesRequest;
use App\Http\Requests\EstimatesRequest;
use App\Http\Resources\EstimateResource;
use App\Jobs\GenerateEstimatePdfJob;
use App\Models\Estimate;
use App\Services\EstimateService;
use Illuminate\Http\Request;

class EstimatesController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $estimates = Estimate::whereCompany()
            ->join('customers', 'customers.id', '=', 'estimates.customer_id')
            ->applyFilters($request->all())
            ->select('estimates.*', 'customers.name')
            ->latest()
            ->paginateData($limit);

        return EstimateResource::collection($estimates)
            ->additional(['meta' => [
                'estimate_total_count' => Estimate::whereCompany()->count(),
            ]]);
    }

    public function store(EstimatesRequest $request)
    {
        $this->authorize('create', Estimate::class);

        $estimate = $this->estimateService->create($request);

        if ($request->has('estimateSend')) {
            $this->estimateService->send($estimate, $request->only(['title', 'body']));
        }

        GenerateEstimatePdfJob::dispatch($estimate);

        return new EstimateResource($estimate);
    }

    public function show(Request $request, Estimate $estimate)
    {
        $this->authorize('view', $estimate);

        return new EstimateResource($estimate);
    }

    public function update(EstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('update', $estimate);

        $estimate = $this->estimateService->update($estimate, $request);

        GenerateEstimatePdfJob::dispatch($estimate, true);

        return new EstimateResource($estimate);
    }

    public function delete(DeleteEstimatesRequest $request)
    {
        $this->authorize('delete multiple estimates');

        $ids = Estimate::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        Estimate::destroy($ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
