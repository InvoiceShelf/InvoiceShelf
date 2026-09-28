<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\PurchaseInputs;
use App\Domains\Purchases\Application\SupplierSettlementService;
use App\Domains\Purchases\Http\Requests\PurchaseActionRequest;
use App\Domains\Purchases\Http\Requests\SupplierAllocationRequest;
use App\Domains\Purchases\Http\Requests\SupplierPaymentRequest;
use App\Domains\Purchases\Http\Resources\SupplierPaymentResource;
use App\Domains\Purchases\Models\SupplierPayment;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierPaymentsController extends Controller
{
    private const RELATIONS = ['supplier', 'currency', 'allocations.bill', 'refunds'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SupplierPayment::class);
        $query = SupplierPayment::query()->forCompany((int) $request->header('company'))->with(self::RELATIONS);
        $query->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id));
        $query->when($request->input('search'), fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'));
        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));
        $query->when($request->input('from_date'), fn ($q, $date) => $q->where('payment_date', '>=', $date));
        $query->when($request->input('to_date'), fn ($q, $date) => $q->where('payment_date', '<=', $date));

        $limit = min(100, max(1, $request->integer('limit', 20)));

        return SupplierPaymentResource::collection($query->orderByDesc('id')->paginate($limit));
    }

    public function show(SupplierPayment $supplierPayment): SupplierPaymentResource
    {
        $this->authorize('view', $supplierPayment);

        return new SupplierPaymentResource($supplierPayment->load(self::RELATIONS));
    }

    public function store(SupplierPaymentRequest $request, SupplierSettlementService $service): SupplierPaymentResource
    {
        $this->authorize('create', SupplierPayment::class);

        $payment = $service->recordPayment((int) $request->header('company'), $request->user()->id, $request->validated());

        return new SupplierPaymentResource($payment->load(self::RELATIONS));
    }

    public function action(PurchaseActionRequest $request, SupplierPayment $supplierPayment, SupplierSettlementService $service): SupplierPaymentResource
    {
        $this->authorize($request->input('action') === 'void' ? 'delete' : 'update', $supplierPayment);
        PurchaseInputs::ensure($request->input('action') === 'void', 'action', 'Only void is supported.');
        $service->void($supplierPayment, (string) $request->input('reason'));

        return new SupplierPaymentResource($supplierPayment->fresh(self::RELATIONS));
    }

    public function allocations(SupplierAllocationRequest $request, SupplierPayment $supplierPayment, SupplierSettlementService $service): SupplierPaymentResource
    {
        $this->authorize('update', $supplierPayment);

        return new SupplierPaymentResource($service->replaceAllocations($supplierPayment, $request->validated('allocations')));
    }
}
