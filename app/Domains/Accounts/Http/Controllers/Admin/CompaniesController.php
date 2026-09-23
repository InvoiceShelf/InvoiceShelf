<?php

namespace App\Domains\Accounts\Http\Controllers\Admin;

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Contracts\CompanyAddressWriter;
use App\Domains\Accounts\Http\Requests\AdminCompanyUpdateRequest;
use App\Domains\Accounts\Http\Requests\CompaniesRequest;
use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Accounts\Models\Company;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

class CompaniesController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
        private readonly CompanyAddressWriter $companyAddressWriter,
    ) {}

    public function index(Request $request)
    {
        $companies = Company::query()
            ->with(['owner', 'address'])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->search.'%');
            })
            ->when($request->has('orderByField') && $request->has('orderBy'), function ($query) use ($request) {
                $query->orderBy($request->orderByField, $request->orderBy);
            }, function ($query) {
                $query->orderBy('name', 'asc');
            })
            ->paginate($request->input('limit', 10));

        return CompanyResource::collection($companies);
    }

    public function show(Company $company)
    {
        $company->load(['owner', 'address']);

        return new CompanyResource($company);
    }

    public function update(AdminCompanyUpdateRequest $request, Company $company)
    {
        $company->update([
            'name' => $request->name,
            'vat_id' => $request->vat_id,
            'tax_id' => $request->tax_id,
            'owner_id' => $request->owner_id,
        ]);

        if ($request->has('address')) {
            $this->companyAddressWriter->upsert($company, $request->validated('address'));
        }

        $company->load(['owner', 'address']);

        return new CompanyResource($company);
    }

    public function store(CompaniesRequest $request)
    {
        $this->authorize('create company');

        $company = $this->companyService->createFor(
            $request->user(),
            $request->getCompanyPayload(),
            (int) $request->validated('currency'),
        );

        if ($request->address) {
            $this->companyAddressWriter->upsert($company, $request->validated('address'));
        }

        return new CompanyResource($company);
    }

    public function destroy(Request $request)
    {
        $company = Company::query()->find($request->header('company'));

        $this->authorize('delete company', $company);

        if ($company->name !== $request->input('name')) {
            return respondJson(
                'company_name_must_match_with_given_name',
                'Company name must match with given name'
            );
        }

        $this->companyService->delete($company);

        return response()->json([
            'success' => true,
        ]);
    }

    public function userCompanies(Request $request)
    {
        return CompanyResource::collection($request->user()->companies);
    }
}
