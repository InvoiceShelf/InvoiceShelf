<?php

namespace App\Http\Controllers\V1\Admin\CreditNote;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendCreditNoteRequest;
use App\Models\CreditNote;

class SendCreditNoteController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(SendCreditNoteRequest $request, CreditNote $creditNote)
    {
        $this->authorize('send credit note', $creditNote);

        $response = $creditNote->send($request->all());
        return response()->json($response);
    }
}
