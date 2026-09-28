<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\PurchaseInputs;
use App\Domains\Purchases\Application\SupplierSettlementService;
use App\Domains\Purchases\Http\Requests\PurchaseActionRequest;
use App\Domains\Purchases\Http\Requests\SupplierRefundRequest;
use App\Domains\Purchases\Http\Resources\SupplierRefundResource;
use App\Domains\Purchases\Models\SupplierRefund;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierRefundsController extends Controller
{
    private const RELATIONS = ['supplier', 'currency'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SupplierRefund::class);
        $query = SupplierRefund::query()->forCompany((int) $request->header('company'))->with(self::RELATIONS);
        $query->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id));
        $query->when($request->input('search'), fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'));
        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));
        $query->when($request->input('from_date'), fn ($q, $date) => $q->where('payment_date', '>=', $date));
        $query->when($request->input('to_date'), fn ($q, $date) => $q->where('payment_date', '<=', $date));

        $limit = min(100, max(1, $request->integer('limit', 20)));

        return SupplierRefundResource::collection($query->orderByDesc('id')->paginate($limit));
    }

    public function show(SupplierRefund $supplierRefund): SupplierRefundResource
    {
        $this->authorize('view', $supplierRefund);

        return new SupplierRefundResource($supplierRefund->load(self::RELATIONS));
    }

    public function store(SupplierRefundRequest $request, SupplierSettlementService $service): SupplierRefundResource
    {
        $this->authorize('create', SupplierRefund::class);

        $refund = $service->recordRefund((int) $request->header('company'), $request->user()->id, $request->validated());

        return new SupplierRefundResource($refund->load(self::RELATIONS));
    }

    public function action(PurchaseActionRequest $request, SupplierRefund $supplierRefund, SupplierSettlementService $service): SupplierRefundResource
    {
        $this->authorize($request->input('action') === 'void' ? 'delete' : 'update', $supplierRefund);
        PurchaseInputs::ensure($request->input('action') === 'void', 'action', 'Only void is supported.');
        $service->void($supplierRefund, (string) $request->input('reason'));

        return new SupplierRefundResource($supplierRefund->fresh(self::RELATIONS));
    }
}
