<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditNoteResource;
use App\Models\EmailLog;
use App\Models\CreditNote;
use Illuminate\Http\Request;

class CreditNotePdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        if (! $emailLog->isExpired()) {
            return $emailLog->mailable->getGeneratedPDFOrStream('credit-note');
        }

        abort(403, 'Link Expired.');
    }

    public function getCreditNote(EmailLog $emailLog)
    {
        $creditNote = CreditNote::find($emailLog->mailable_id);

        return new CreditNoteResource($creditNote);
    }
}
