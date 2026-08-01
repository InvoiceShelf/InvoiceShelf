<?php

namespace App\Http\Controllers\CustomerPortal\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $invoices = Invoice::with(['items', 'customer', 'creator', 'taxes'])
            ->where('status', '<>', 'DRAFT')
            ->applyFilters($request->all())
            ->whereCustomer(Auth::guard('customer')->id())
            ->latest()
            ->paginateData($limit);

        return InvoiceResource::collection($invoices)
            ->additional(['meta' => [
                // Issued invoices only: a credit note is a reversal document,
                // not another invoice the customer received.
                'invoiceTotalCount' => Invoice::where('type', Invoice::TYPE_INVOICE)->where('status', '<>', 'DRAFT')->whereCustomer(Auth::guard('customer')->id())->count(),
            ]]);
    }

    public function show(Company $company, $id)
    {
        $invoice = $company->invoices()
            ->whereCustomer(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $invoice) {
            return response()->json(['error' => 'invoice_not_found'], 404);
        }

        return new InvoiceResource($invoice);
    }
}
