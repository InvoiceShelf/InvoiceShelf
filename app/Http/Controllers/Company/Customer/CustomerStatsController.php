<?php

namespace App\Http\Controllers\Company\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerStatsController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {}

    public function __invoke(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $chartData = $this->customerService->getStats(
            $customer,
            $request->header('company'),
            $request->has('previous_year')
        );

        $customer = Customer::find($customer->id);

        return (new CustomerResource($customer))
            ->additional(['meta' => [
                'chartData' => $chartData,
            ]]);
    }
}
