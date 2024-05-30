<?php

namespace App\Http\Controllers\V1\Customer\Payment;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\PaymentMethodResource;
use App\Models\Company;
use App\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Company $company)
    {
        return PaymentMethodResource::collection(PaymentMethod::where('company_id', $company->id)->get());
    }
}
