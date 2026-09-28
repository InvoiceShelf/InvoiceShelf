<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\SupplierService;
use App\Domains\Purchases\Http\Requests\SupplierRequest;
use App\Domains\Purchases\Http\Resources\SupplierResource;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Reporting\Queries\PurchasesQuery;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SuppliersController extends Controller
{
    private const RELATIONS = ['fields.customField', 'currency'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);
        $query = Supplier::query()->forCompany((int) $request->header('company'))->with(self::RELATIONS);
        $query->when($request->input('search'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'));

        $limit = min(100, max(1, $request->integer('limit', 20)));

        return SupplierResource::collection($query->orderByDesc('id')->paginate($limit));
    }

    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);
        $supplier->setAttribute('balances', app(PurchasesQuery::class)->supplierBalances($supplier));

        return new SupplierResource($supplier->load(self::RELATIONS));
    }

    public function store(SupplierRequest $request, SupplierService $service): SupplierResource
    {
        $this->authorize('create', Supplier::class);

        $supplier = $service->save(null, (int) $request->header('company'), $request->user()->id, $request->validated());

        return new SupplierResource($supplier->load(self::RELATIONS));
    }

    public function update(SupplierRequest $request, Supplier $supplier, SupplierService $service): SupplierResource
    {
        $this->authorize('update', $supplier);

        $supplier = $service->save($supplier, (int) $request->header('company'), $request->user()->id, $request->validated());

        return new SupplierResource($supplier->load(self::RELATIONS));
    }
}
