<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use Illuminate\Http\Request;

class CreditNotePdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, CreditNote $creditNote)
    {
        if ($request->has('preview')) {
            return view('app.pdf.credit-note.credit-note');
        }

        return $creditNote->getGeneratedPDFOrStream('credit-note');
    }
}
