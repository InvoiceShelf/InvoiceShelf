<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Purchases\Application\PurchaseDocumentService;
use App\Domains\Purchases\Http\Requests\BillRequest;
use App\Domains\Purchases\Http\Requests\PurchaseActionRequest;
use App\Domains\Purchases\Http\Resources\BillResource;
use App\Domains\Purchases\Models\Bill;
use App\Platform\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillsController extends Controller
{
    private const RELATIONS = ['fields.customField', 'supplier', 'currency', 'items', 'media'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Bill::class);
        $query = Bill::query()->forCompany((int) $request->header('company'))->with(self::RELATIONS);
        $query->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id));
        $query->when($request->input('search'), fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'));
        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));
        $query->when($request->input('from_date'), fn ($q, $date) => $q->where('document_date', '>=', $date))->when($request->input('to_date'), fn ($q, $date) => $q->where('document_date', '<=', $date));
        $query->when($request->boolean('outstanding'), fn ($q) => $q->where('status', 'OPEN')->where('due_amount', '>', 0));

        $settlement = $request->input('settlement_status');
        if ($settlement) {
            $query->where('status', 'OPEN');
            match ($settlement) {
                'UNPAID' => $query->whereColumn('due_amount', 'total')->where('due_amount', '>', 0),
                'PARTIAL' => $query->where('due_amount', '>', 0)->whereColumn('due_amount', '<', 'total'),
                'SETTLED' => $query->where('due_amount', 0),
                'OVERDUE' => $query->where('due_amount', '>', 0)->where('due_date', '<', CarbonImmutable::now(CompanySetting::getSetting('time_zone', $request->header('company')) ?: config('app.timezone'))->toDateString()),
                default => null,
            };
        }

        return BillResource::collection($query->orderByDesc('id')->paginate(min(100, max(1, $request->integer('limit', 20)))));
    }

    public function show(Bill $bill): BillResource
    {
        $this->authorize('view', $bill);
        $bill->load(['paymentAllocations.payment', 'creditAllocations.credit', 'credits.items']);

        return new BillResource($bill->load(self::RELATIONS));
    }

    public function store(BillRequest $request, PurchaseDocumentService $service): BillResource
    {
        $this->authorize('create', Bill::class);

        return new BillResource($service->saveBill(null, (int) $request->header('company'), $request->user()->id, $request->validated())->load(self::RELATIONS));
    }

    public function update(BillRequest $request, Bill $bill, PurchaseDocumentService $service): BillResource
    {
        $this->authorize('update', $bill);

        return new BillResource($service->saveBill($bill, (int) $request->header('company'), $request->user()->id, $request->validated())->load(self::RELATIONS));
    }

    public function action(PurchaseActionRequest $request, Bill $bill, PurchaseDocumentService $service): BillResource
    {
        $this->authorize($request->input('action') === 'void' ? 'delete' : 'update', $bill);
        $service->act($bill, $request->input('action'), $request->input('reason'), $request->user()->id);

        return new BillResource($bill->fresh(self::RELATIONS));
    }
}
