<?php

namespace App\Http\Controllers\V1\Customer\CreditNote;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\PaymentResource;
use App\Models\Company;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreditNotesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $creditNotes = CreditNote::with(['customer', 'invoice', 'creator'])
            ->whereCustomer(Auth::guard('customer')->id())
            ->leftJoin('invoices', 'invoices.id', '=', 'credit_notes.invoice_id')
            ->applyFilters($request->only([
                'credit_note_number',
                'orderByField',
                'orderBy',
            ]))
            ->select('credit_notes.*', 'invoices.invoice_number')
            ->latest()
            ->paginateData($limit);

        return CreditNoteResource::collection($creditNotes)
            ->additional(['meta' => [
                'creditNoteTotalCount' => CreditNote::whereCustomer(Auth::guard('customer')->id())->count(),
            ]]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CreditNote  $creditNote
     * @return \Illuminate\Http\Response
     */
    public function show(Company $company, $id)
    {
        $creditNote = $company->creditNotes()
            ->whereCustomer(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $creditNote) {
            return response()->json(['error' => 'credit_note_not_found'], 404);
        }

        return new CreditNoteResource($creditNote);
    }
}
