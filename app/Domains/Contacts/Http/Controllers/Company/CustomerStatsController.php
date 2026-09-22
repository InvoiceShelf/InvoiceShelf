<?php

namespace App\Domains\Contacts\Http\Controllers\Company;

use App\Domains\Contacts\Contracts\CustomerStatsProvider;
use App\Domains\Contacts\Http\Requests\CustomerStatsRequest;
use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Reporting\Queries\CustomerStatementQuery;
use App\Platform\Http\Controller;

/**
 * The money chart shown on one contact's page.
 *
 * The period is the company's fiscal year, the one before it with a
 * `previous_year` key (its presence alone counts), or the `from_date`/`to_date`
 * range the request names.
 *
 * The company comes off the request header rather than the resolved company,
 * exactly as it reaches the provider's integer parameter.
 */
class CustomerStatsController extends Controller
{
    public function __construct(
        private readonly CustomerStatsProvider $customerStatsProvider,
        private readonly CustomerStatementQuery $customerStatementQuery,
    ) {}

    public function __invoke(CustomerStatsRequest $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $companyId = $request->header('company');

        $chartData = $this->customerStatsProvider->get(
            $customer,
            $companyId,
            $request->has('previous_year'),
            $request->validated('from_date'),
            $request->validated('to_date'),
        );

        // The row is read a second time instead of reusing the bound instance.
        // Nothing the provider does requires it, but the reload is what the
        // resource ends up rendering, so it stays.
        $fresh = Customer::query()->find($customer->id);

        $this->customerStatementQuery->hydrateAccountSummaries([$fresh]);

        return (new CustomerResource($fresh))
            ->additional(['meta' => ['chartData' => $chartData]]);
    }
}
