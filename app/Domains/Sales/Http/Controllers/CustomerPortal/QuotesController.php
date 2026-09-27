<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Sales\Application\QuoteService;
use App\Domains\Sales\Http\Resources\CustomerPortal\QuoteResource;
use App\Domains\Sales\Models\Quote;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class QuotesController extends Controller
{
    /**
     * Page through the offers addressed to the signed-in contact.
     *
     * Unsent work stays private to the issuer, so drafts reach neither the
     * page itself nor the counter beside it.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $perPage = 10;

        if ($request->has('limit')) {
            $perPage = $request->limit;
        }

        $contact = Auth::guard('customer')->id();

        $query = Quote::with(['items', 'customer', 'taxes', 'creator'])
            ->where('status', '<>', Quote::STATUS_DRAFT)
            ->whereCustomer($contact);

        $query->applyFilters($request->only([
            'status',
            'quote_number',
            'from_date',
            'to_date',
            'orderByField',
            'orderBy',
        ]));

        $page = $query->latest()->paginateData($perPage);

        $visible = Quote::query()
            ->where('status', '<>', Quote::STATUS_DRAFT)
            ->whereCustomer($contact)
            ->count();

        return QuoteResource::collection($page)
            ->additional(['meta' => [
                'quoteTotalCount' => $visible,
            ]]);
    }

    /**
     * Hand back a single offer, looked up inside the portal's company and
     * narrowed to the signed-in contact so ids cannot be probed.
     *
     * @param  string  $id
     * @return Response
     */
    public function show(Company $company, $id)
    {
        $contact = Auth::guard('customer')->id();

        $quote = $company->quotes()->whereCustomer($contact)->where('status', '<>', Quote::STATUS_DRAFT)->where('id', $id)->first();

        if ($quote === null) {
            return response()->json(['error' => 'quote_not_found'], Response::HTTP_NOT_FOUND);
        }

        app(QuoteService::class)->recordView($quote);

        return QuoteResource::make($quote);
    }
}
