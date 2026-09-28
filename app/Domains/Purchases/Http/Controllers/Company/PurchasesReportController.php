<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Accounts\Models\Company;
use App\Domains\Purchases\Http\Requests\PurchaseReportRequest;
use App\Domains\Reporting\Queries\PurchasesQuery;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;

class PurchasesReportController extends Controller
{
    public function __invoke(PurchaseReportRequest $request, PurchasesQuery $query): JsonResponse
    {
        $this->authorize('view report', Company::findOrFail($request->header('company')));

        return response()->json(['data' => $query->report((int) $request->header('company'), $request->validated('from_date'), $request->validated('to_date'), $request->integer('supplier_id') ?: null)]);
    }
}
