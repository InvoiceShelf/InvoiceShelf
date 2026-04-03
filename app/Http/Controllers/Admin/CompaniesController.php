<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Hashids;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCompanyUpdateRequest;
use App\Http\Requests\CompaniesRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class CompaniesController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
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
            $company->address()->updateOrCreate(
                ['company_id' => $company->id],
                $request->address,
            );
        }

        $company->load(['owner', 'address']);

        return new CompanyResource($company);
    }

    public function store(CompaniesRequest $request)
    {
        $this->authorize('create company');

        $user = $request->user();

        $company = Company::create($request->getCompanyPayload());
        $company->unique_hash = Hashids::connection(Company::class)->encode($company->id);
        $company->save();
        $this->companyService->setupDefaults($company);
        $user->companies()->attach($company->id);

        BouncerFacade::scope()->to($company->id);
        $user->assign('owner');

        if ($request->address) {
            $company->address()->create($request->address);
        }

        return new CompanyResource($company);
    }

    public function destroy(Request $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('delete company', $company);

        $user = $request->user();

        if ($request->name !== $company->name) {
            return respondJson('company_name_must_match_with_given_name', 'Company name must match with given name');
        }

        if ($user->loadCount('companies')->companies_count <= 1) {
            return respondJson('You_cannot_delete_all_companies', 'You cannot delete all companies');
        }

        $this->companyService->delete($company, $user);

        return response()->json([
            'success' => true,
        ]);
    }

    public function userCompanies(Request $request)
    {
        $companies = $request->user()->companies;

        return CompanyResource::collection($companies);
    }
}
