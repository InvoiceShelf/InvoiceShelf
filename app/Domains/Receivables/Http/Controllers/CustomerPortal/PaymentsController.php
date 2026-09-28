<?php

namespace App\Domains\Receivables\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Receivables\Http\Resources\CustomerPortal\PaymentResource;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PaymentsController extends Controller
{
    /**
     * Page through the receipts recorded against the signed-in contact.
     *
     * Only four of the admin filters reach the model here — the number, the
     * method and the two ordering knobs. Everything else in the query string
     * is dropped before the query is built, so the portal cannot be talked
     * into widening its own view of the books.
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

        $narrowing = $request->only([
            'payment_number',
            'payment_method_id',
            'orderByField',
            'orderBy',
        ]);

        $page = Payment::with(['customer', 'allocations.invoice', 'paymentMethod', 'creator'])
            ->whereCustomer($contact)
            ->applyFilters($narrowing)
            ->select('customer_payments.*')
            ->orderByDesc('created_at')
            ->paginateData($perPage);

        // Counted afresh instead of taken off the page: the tally covers
        // everything on file for the contact, filters and paging aside.
        $recorded = Payment::whereCustomer($contact)->count();

        return PaymentResource::collection($page)->additional([
            'meta' => ['paymentTotalCount' => $recorded],
        ]);
    }

    /**
     * Hand back a single receipt, looked up inside the portal's company and
     * narrowed to the signed-in contact.
     *
     * @param  string  $id
     * @return Response
     */
    public function show(Company $company, $id)
    {
        $contact = Auth::guard('customer')->id();

        $payment = $company->payments()->whereCustomer($contact)->where('id', $id)->first();

        if ($payment === null) {
            return response()->json(['error' => 'payment_not_found'], Response::HTTP_NOT_FOUND);
        }

        return PaymentResource::make($payment->load(['allocations.invoice']));
    }
}
