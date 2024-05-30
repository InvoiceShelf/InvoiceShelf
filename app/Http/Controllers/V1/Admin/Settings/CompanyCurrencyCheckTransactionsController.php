<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyCurrencyCheckTransactionsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('manage company', $company);

        return response()->json([
            'has_transactions' => $company->hasTransactions(),
        ]);
    }
}
