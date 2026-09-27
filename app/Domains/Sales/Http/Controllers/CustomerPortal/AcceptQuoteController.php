<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Sales\Application\QuoteService;
use App\Domains\Sales\Http\Requests\RespondToQuoteRequest;
use App\Domains\Sales\Http\Resources\CustomerPortal\QuoteResource;
use App\Platform\Http\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AcceptQuoteController extends Controller
{
    public function __invoke(RespondToQuoteRequest $request, Company $company, $id)
    {
        $contact = Auth::guard('customer')->id();

        $quote = $company->quotes()->whereCustomer($contact)->where('id', $id)->first();

        if ($quote === null) {
            return response()->json(['error' => 'quote_not_found'], Response::HTTP_NOT_FOUND);
        }

        app(QuoteService::class)->respond($quote, $request->validated('status'));

        return QuoteResource::make($quote);
    }
}
