<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Sales\Events\EstimateAnswered;
use App\Domains\Sales\Http\Requests\AnswerEstimateRequest;
use App\Domains\Sales\Http\Resources\CustomerPortal\EstimateResource;
use App\Platform\Http\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AcceptEstimateController extends Controller
{
    /**
     * Record the contact's verdict on one of their own offers, accepted or
     * rejected, and let the company know when it is a new one.
     *
     * @param  string  $id
     * @return Response
     */
    public function __invoke(AnswerEstimateRequest $request, Company $company, $id)
    {
        $contact = Auth::guard('customer')->id();

        $estimate = $company->estimates()->whereCustomer($contact)->where('id', $id)->first();

        if ($estimate === null) {
            return response()->json(['error' => 'estimate_not_found'], Response::HTTP_NOT_FOUND);
        }

        $status = (string) $request->validated('status');
        $changed = $estimate->status !== $status;

        $estimate->update(['status' => $status]);

        if ($changed) {
            EstimateAnswered::dispatch((int) $estimate->id, (int) $estimate->company_id, $status);
        }

        return EstimateResource::make($estimate);
    }
}
