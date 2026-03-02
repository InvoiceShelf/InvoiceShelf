<?php

namespace App\Http\Controllers\V1\Admin\CreditNote;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteCreditNotesRequest;
use App\Http\Requests\CreditNoteRequest;
use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use Illuminate\Http\Request;

class CreditNotesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CreditNote::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $creditNotes = CreditNote::whereCompany()
            ->join('customers', 'customers.id', '=', 'credit_notes.customer_id')
            ->leftJoin('invoices', 'invoices.id', '=', 'credit_notes.invoice_id')
            ->applyFilters($request->all())
            ->select('credit_notes.*', 'customers.name', 'invoices.invoice_number')
            ->latest()
            ->paginateData($limit);

        return CreditNoteResource::collection($creditNotes)
            ->additional(['meta' => [
                'credit_note_total_count' => CreditNote::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreditNoteRequest $request)
    {
        $this->authorize('create', CreditNote::class);

        $creditNote = CreditNote::createCreditNote($request);

        return new CreditNoteResource($creditNote);
    }

    public function show(Request $request, CreditNote $creditNote)
    {
        $this->authorize('view', $creditNote);

        return new CreditNoteResource($creditNote);
    }

    public function update(CreditNoteRequest $request, CreditNote $creditNote)
    {
        $this->authorize('update', $creditNote);
        $creditNote = $creditNote->updateCreditNote($request);

        return new CreditNoteResource($creditNote);
    }

    public function delete(DeleteCreditNotesRequest $request)
    {
        $this->authorize('delete multiple credit notes');

        CreditNote::deleteCreditNotes($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}