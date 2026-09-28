<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\PurchaseDocumentService;
use App\Domains\Purchases\Application\SupplierSettlementService;
use App\Domains\Purchases\Http\Requests\PurchaseActionRequest;
use App\Domains\Purchases\Http\Requests\SupplierAllocationRequest;
use App\Domains\Purchases\Http\Requests\SupplierCreditRequest;
use App\Domains\Purchases\Http\Resources\SupplierCreditResource;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierCreditsController extends Controller
{
    private const RELATIONS = ['supplier', 'currency', 'items', 'media', 'allocations.bill', 'refunds'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SupplierCredit::class);
        $query = SupplierCredit::query()->forCompany((int) $request->header('company'))->with(self::RELATIONS);
        $query->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id));
        $query->when($request->input('search'), fn ($q, $term) => $q->where('number', 'like', '%'.$term.'%'));
        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));
        $query->when($request->input('from_date'), fn ($q, $date) => $q->where('document_date', '>=', $date));
        $query->when($request->input('to_date'), fn ($q, $date) => $q->where('document_date', '<=', $date));

        $limit = min(100, max(1, $request->integer('limit', 20)));

        return SupplierCreditResource::collection($query->orderByDesc('id')->paginate($limit));
    }

    public function show(SupplierCredit $supplierCredit): SupplierCreditResource
    {
        $this->authorize('view', $supplierCredit);

        return new SupplierCreditResource($supplierCredit->load(self::RELATIONS));
    }

    public function store(SupplierCreditRequest $request, PurchaseDocumentService $service): SupplierCreditResource
    {
        $this->authorize('create', SupplierCredit::class);

        $credit = $service->createCredit((int) $request->header('company'), $request->user()->id, $request->validated());

        return new SupplierCreditResource($credit->load(self::RELATIONS));
    }

    public function action(PurchaseActionRequest $request, SupplierCredit $supplierCredit, PurchaseDocumentService $service): SupplierCreditResource
    {
        $this->authorize($request->input('action') === 'void' ? 'delete' : 'update', $supplierCredit);
        $service->act($supplierCredit, $request->input('action'), $request->input('reason'));

        return new SupplierCreditResource($supplierCredit->fresh(self::RELATIONS));
    }

    public function allocations(SupplierAllocationRequest $request, SupplierCredit $supplierCredit, SupplierSettlementService $service): SupplierCreditResource
    {
        $this->authorize('update', $supplierCredit);

        return new SupplierCreditResource($service->replaceAllocations($supplierCredit, $request->validated('allocations')));
    }
}
