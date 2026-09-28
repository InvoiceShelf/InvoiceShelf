<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\RecurringCostService;
use App\Domains\Purchases\Http\Requests\PurchaseActionRequest;
use App\Domains\Purchases\Http\Requests\PurchaseListRequest;
use App\Domains\Purchases\Http\Requests\RecurringCostRequest;
use App\Domains\Purchases\Http\Resources\RecurringCostResource;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\RecurringCost;
use App\Platform\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecurringCostsController extends Controller
{
    private const RELATIONS = ['supplier'];

    public function index(PurchaseListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RecurringCost::class);
        $query = RecurringCost::query()->forCompany((int) $request->header('company'))->with(self::RELATIONS);
        $query->when($request->validated('mode'), fn ($q, $mode) => $q->where('mode', $mode));
        $query->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id));
        $query->when($request->input('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'));
        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return RecurringCostResource::collection($query->orderByDesc('id')->paginate(min(100, max(1, $request->integer('limit', 20)))));
    }

    public function show(RecurringCost $recurringCost): RecurringCostResource
    {
        $this->authorize('view', $recurringCost);
        $recurringCost->load('occurrences');

        return new RecurringCostResource($recurringCost->load([...self::RELATIONS, 'activities']));
    }

    public function store(RecurringCostRequest $request, RecurringCostService $service): RecurringCostResource
    {
        $this->authorize('create', RecurringCost::class);
        if ($request->input('mode') === 'EXPENSE') {
            $this->authorize('create', Expense::class);
        }

        return new RecurringCostResource($service->save(null, (int) $request->header('company'), $request->user()->id, $request->validated())->load(self::RELATIONS));
    }

    public function update(RecurringCostRequest $request, RecurringCost $recurringCost, RecurringCostService $service): RecurringCostResource
    {
        $this->authorize('update', $recurringCost);
        if ($request->input('mode') === 'EXPENSE') {
            $this->authorize('create', Expense::class);
        }

        return new RecurringCostResource($service->save($recurringCost, (int) $request->header('company'), $request->user()->id, $request->validated())->load(self::RELATIONS));
    }

    public function action(PurchaseActionRequest $request, RecurringCost $recurringCost, RecurringCostService $service): RecurringCostResource
    {
        $this->authorize($request->input('action') === 'void' ? 'delete' : 'update', $recurringCost);
        $service->act($recurringCost, $request->input('action'), $request->input('reason'), $request->user()->id);

        return new RecurringCostResource($recurringCost->fresh(self::RELATIONS));
    }
}
