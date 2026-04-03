<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCompanyUpdateRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;

class CompaniesController extends Controller
{
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
}
