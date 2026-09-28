<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\RecurringCostService;
use App\Domains\Purchases\Http\Requests\RecurringCostActionRequest;
use App\Domains\Purchases\Http\Requests\RecurringCostRequest;
use App\Domains\Purchases\Http\Resources\RecurringCostResource;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\RecurringCost;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecurringCostsController extends Controller
{
    private const RELATIONS = ['supplier'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RecurringCost::class);

        $query = RecurringCost::query()->forCompany((int) $request->header('company'))->with(self::RELATIONS);
        $query->when($request->input('mode'), fn ($q, $mode) => $q->where('mode', $mode));
        $query->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id));
        $query->when($request->input('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'));
        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        $limit = min(100, max(1, $request->integer('limit', 20)));

        return RecurringCostResource::collection($query->orderByDesc('id')->paginate($limit));
    }

    public function show(RecurringCost $recurringCost): RecurringCostResource
    {
        $this->authorize('view', $recurringCost);

        return new RecurringCostResource($recurringCost->load([...self::RELATIONS, 'occurrences.record']));
    }

    public function store(RecurringCostRequest $request, RecurringCostService $service): RecurringCostResource
    {
        $this->authorize('create', RecurringCost::class);
        $this->authorizeRecords($request);

        $schedule = $service->save(null, (int) $request->header('company'), $request->user()->id, $request->validated());

        return new RecurringCostResource($schedule->load(self::RELATIONS));
    }

    public function update(RecurringCostRequest $request, RecurringCost $recurringCost, RecurringCostService $service): RecurringCostResource
    {
        $this->authorize('update', $recurringCost);
        $this->authorizeRecords($request);

        $schedule = $service->save($recurringCost, (int) $request->header('company'), $request->user()->id, $request->validated());

        return new RecurringCostResource($schedule->load(self::RELATIONS));
    }

    public function action(RecurringCostActionRequest $request, RecurringCost $recurringCost, RecurringCostService $service): RecurringCostResource
    {
        $this->authorize('update', $recurringCost);

        $schedule = $service->act($recurringCost, $request->validated('action'));

        return new RecurringCostResource($schedule->load(self::RELATIONS));
    }

    public function destroy(RecurringCost $recurringCost): JsonResponse
    {
        $this->authorize('delete', $recurringCost);

        // What it generated stays; only the schedule and its run log go.
        $recurringCost->occurrences()->delete();
        $recurringCost->delete();

        return response()->json(['success' => true]);
    }

    /**
     * A schedule makes records on its creator's behalf, so setting one up
     * needs the right to make those records too.
     */
    private function authorizeRecords(RecurringCostRequest $request): void
    {
        $request->input('mode') === RecurringCost::MODE_EXPENSE
            ? $this->authorize('create', Expense::class)
            : $this->authorize('create', Bill::class);
    }
}
